<?php

namespace App\Services;

use App\Models\Hospital;
use App\Models\Invoice;
use App\Models\MedicineBatch;
use App\Models\PharmacySale;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PharmacyDispenseService
{
    public function __construct(
        private TenantContext $tenant,
        private InvoiceService $invoices,
        private AuditService $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function dispense(array $data): PharmacySale
    {
        return DB::transaction(function () use ($data): PharmacySale {
            $payloadHash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            $existing = PharmacySale::where('hospital_id', $this->tenant->hospitalId())->where('request_key', $data['request_key'])->first();
            if ($existing) {
                if ($existing->payload_hash !== $payloadHash) {
                    throw ValidationException::withMessages(['request_key' => 'This request key belongs to a different pharmacy sale.']);
                }

                return $existing->load($this->relations());
            }

            $prescription = Prescription::where('hospital_id', $this->tenant->hospitalId())
                ->where('branch_id', $this->tenant->branchId())->with('patient')->lockForUpdate()->findOrFail($data['prescription_id']);
            $prescriptionItems = PrescriptionItem::where('prescription_id', $prescription->id)
                ->whereIn('id', collect($data['items'])->pluck('prescription_item_id'))->with('medicine')->get()->keyBy('id');
            if ($prescriptionItems->count() !== count($data['items']) || $prescriptionItems->contains(fn (PrescriptionItem $item) => $item->medicine_id === null)) {
                throw ValidationException::withMessages(['items' => 'Every line must be a catalog medicine from this prescription.']);
            }
            if (DB::table('pharmacy_sale_items')->whereIn('prescription_item_id', $prescriptionItems->keys())->exists()) {
                throw ValidationException::withMessages(['items' => 'One or more prescription lines have already been dispensed.']);
            }

            $batches = MedicineBatch::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())
                ->whereIn('id', collect($data['items'])->pluck('medicine_batch_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($batches->count() !== collect($data['items'])->pluck('medicine_batch_id')->unique()->count()) {
                throw ValidationException::withMessages(['items' => 'Every batch must belong to the active branch.']);
            }

            $subtotal = $tax = 0;
            $prepared = [];
            $today = now($this->tenant->branch()->timezone)->toDateString();
            foreach ($data['items'] as $index => $entry) {
                $prescriptionItem = $prescriptionItems->get((int) $entry['prescription_item_id']);
                $batch = $batches->get((int) $entry['medicine_batch_id']);
                $quantity = (int) $entry['quantity'];
                if ($batch->medicine_id !== $prescriptionItem->medicine_id) {
                    throw ValidationException::withMessages(["items.{$index}.medicine_batch_id" => 'The selected batch does not match the prescribed medicine.']);
                }
                if ($batch->status !== 'active' || $batch->expires_on->format('Y-m-d') <= $today) {
                    throw ValidationException::withMessages(["items.{$index}.medicine_batch_id" => 'Expired or inactive batches cannot be dispensed.']);
                }
                if ((float) $batch->on_hand_quantity < $quantity) {
                    throw ValidationException::withMessages(["items.{$index}.quantity" => 'The selected batch does not have enough stock.']);
                }
                $lineSubtotal = $quantity * $this->cents($batch->sale_price);
                $lineTax = (int) round($lineSubtotal * (float) $prescriptionItem->medicine->tax_rate_percent / 100);
                $subtotal += $lineSubtotal;
                $tax += $lineTax;
                $prepared[] = compact('prescriptionItem', 'batch', 'quantity', 'lineSubtotal', 'lineTax');
            }

            $invoice = Invoice::create(['hospital_id' => $this->tenant->hospitalId(), 'branch_id' => $this->tenant->branchId(), 'patient_id' => $prescription->patient_id, 'appointment_id' => null, 'created_by' => auth()->id()]);
            $invoice->subtotal = $this->money($subtotal);
            $invoice->tax_total = $this->money($tax);
            $invoice->total = $this->money($subtotal + $tax);
            $invoice->saveOrFail();
            Hospital::whereKey($this->tenant->hospitalId())->lockForUpdate()->firstOrFail();
            $year = (int) now($this->tenant->branch()->timezone)->format('Y');
            $sequence = DB::table('pharmacy_sale_number_sequences')->where('hospital_id', $this->tenant->hospitalId())->where('year', $year)->first();
            $next = ($sequence?->last_number ?? 0) + 1;
            DB::table('pharmacy_sale_number_sequences')->updateOrInsert(['hospital_id' => $this->tenant->hospitalId(), 'year' => $year], ['last_number' => $next]);
            $sale = PharmacySale::create([
                'hospital_id' => $this->tenant->hospitalId(), 'branch_id' => $this->tenant->branchId(), 'patient_id' => $prescription->patient_id,
                'prescription_id' => $prescription->id, 'invoice_id' => $invoice->id, 'number' => sprintf('PHS-%d-%d-%06d', $this->tenant->hospitalId(), $year, $next),
                'status' => 'DISPENSED', 'currency' => 'INR', 'subtotal' => $this->money($subtotal), 'tax_amount' => $this->money($tax), 'total' => $this->money($subtotal + $tax),
                'request_key' => $data['request_key'], 'payload_hash' => $payloadHash, 'dispensed_by' => auth()->id(), 'dispensed_at' => now(),
            ]);

            foreach ($prepared as $entry) {
                /** @var PrescriptionItem $prescriptionItem */
                $prescriptionItem = $entry['prescriptionItem'];
                /** @var MedicineBatch $batch */
                $batch = $entry['batch'];
                $invoiceLine = $invoice->lines()->create([
                    'service_item_id' => null, 'service_code' => $prescriptionItem->medicine->code, 'description' => $prescriptionItem->medicine_name,
                    'quantity' => $entry['quantity'], 'unit_price' => $batch->sale_price, 'discount_type' => 'NONE', 'discount_value' => '0.00',
                    'tax_rate_percent' => $prescriptionItem->medicine->tax_rate_percent, 'subtotal' => $this->money($entry['lineSubtotal']),
                    'discount_amount' => '0.00', 'tax_amount' => $this->money($entry['lineTax']), 'total' => $this->money($entry['lineSubtotal'] + $entry['lineTax']),
                ]);
                $saleItem = $sale->items()->create([
                    'prescription_item_id' => $prescriptionItem->id, 'medicine_id' => $batch->medicine_id, 'medicine_batch_id' => $batch->id, 'invoice_line_id' => $invoiceLine->id,
                    'medicine_code' => $prescriptionItem->medicine->code, 'medicine_name' => $prescriptionItem->medicine_name, 'batch_number' => $batch->batch_number,
                    'expires_on' => $batch->expires_on, 'quantity' => $entry['quantity'], 'unit_price' => $batch->sale_price,
                    'tax_rate_percent' => $prescriptionItem->medicine->tax_rate_percent, 'subtotal' => $this->money($entry['lineSubtotal']),
                    'tax_amount' => $this->money($entry['lineTax']), 'total' => $this->money($entry['lineSubtotal'] + $entry['lineTax']),
                ]);
                $balance = (float) $batch->on_hand_quantity - $entry['quantity'];
                $batch->update(['on_hand_quantity' => number_format($balance, 3, '.', '')]);
                $batch->movements()->create([
                    'hospital_id' => $this->tenant->hospitalId(), 'branch_id' => $this->tenant->branchId(), 'medicine_id' => $batch->medicine_id,
                    'pharmacy_sale_item_id' => $saleItem->id, 'type' => 'DISPENSE', 'quantity' => number_format(-$entry['quantity'], 3, '.', ''),
                    'balance_after' => number_format($balance, 3, '.', ''), 'reference_type' => PharmacySale::class, 'reference_id' => $sale->id,
                    'recorded_by' => auth()->id(), 'recorded_at' => now(),
                ]);
            }
            $this->invoices->issue($invoice);
            $this->audit->record('pharmacy_sales', 'dispensed', $sale, null, $sale->toArray());

            return $sale->load($this->relations());
        }, 3);
    }

    /** @return array<int, string> */
    public function relations(): array
    {
        return ['patient:id,uhid,first_name,last_name', 'prescription.prescriber:id,name', 'dispensedBy:id,name', 'items.batch', 'invoice.lines', 'invoice.payments', 'invoice.financialAdjustments'];
    }

    private function cents(string $value): int
    {
        return (int) round((float) $value * 100);
    }

    private function money(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
