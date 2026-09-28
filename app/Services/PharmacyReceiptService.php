<?php

namespace App\Services;

use App\Models\Hospital;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\PharmacyPurchase;
use App\Models\PharmacySupplier;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PharmacyReceiptService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit) {}

    /** @param array<string, mixed> $data */
    public function receive(array $data): PharmacyPurchase
    {
        return DB::transaction(function () use ($data): PharmacyPurchase {
            $payloadHash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            $existing = PharmacyPurchase::where('hospital_id', $this->tenant->hospitalId())->where('request_key', $data['request_key'])->first();
            if ($existing) {
                if ($existing->payload_hash !== $payloadHash) {
                    throw ValidationException::withMessages(['request_key' => 'This request key belongs to a different receipt.']);
                }

                return $existing->load($this->relations());
            }
            $supplier = PharmacySupplier::where('hospital_id', $this->tenant->hospitalId())->where('status', 'active')->findOrFail($data['pharmacy_supplier_id']);
            if (PharmacyPurchase::where('hospital_id', $this->tenant->hospitalId())->where('pharmacy_supplier_id', $supplier->id)->where('supplier_invoice_number', trim($data['supplier_invoice_number']))->exists()) {
                throw ValidationException::withMessages(['supplier_invoice_number' => 'This supplier invoice has already been received.']);
            }
            $medicines = Medicine::where('hospital_id', $this->tenant->hospitalId())->where('status', 'active')->whereIn('id', collect($data['items'])->pluck('medicine_id'))->with('purchaseUnit')->get()->keyBy('id');
            if ($medicines->count() !== collect($data['items'])->pluck('medicine_id')->unique()->count()) {
                throw ValidationException::withMessages(['items' => 'Every medicine must be active and belong to this hospital.']);
            }
            Hospital::whereKey($this->tenant->hospitalId())->lockForUpdate()->firstOrFail();
            $year = (int) now($this->tenant->branch()->timezone)->format('Y');
            $sequence = DB::table('pharmacy_purchase_number_sequences')->where('hospital_id', $this->tenant->hospitalId())->where('year', $year)->first();
            $next = ($sequence?->last_number ?? 0) + 1;
            DB::table('pharmacy_purchase_number_sequences')->updateOrInsert(['hospital_id' => $this->tenant->hospitalId(), 'year' => $year], ['last_number' => $next]);
            $purchase = PharmacyPurchase::create([
                'hospital_id' => $this->tenant->hospitalId(), 'branch_id' => $this->tenant->branchId(), 'pharmacy_supplier_id' => $supplier->id,
                'number' => sprintf('PUR-%d-%d-%06d', $this->tenant->hospitalId(), $year, $next), 'supplier_invoice_number' => trim($data['supplier_invoice_number']),
                'purchase_date' => $data['purchase_date'], 'status' => 'RECEIVED', 'currency' => 'INR', 'subtotal' => '0.00', 'tax_amount' => '0.00', 'total' => '0.00',
                'request_key' => $data['request_key'], 'payload_hash' => $payloadHash, 'received_by' => auth()->id(), 'received_at' => now(),
            ]);
            $subtotal = $tax = 0;
            foreach ($data['items'] as $index => $entry) {
                $medicine = $medicines->get((int) $entry['medicine_id']);
                $lineSubtotal = (int) round((float) $entry['quantity'] * $this->cents($entry['unit_cost']));
                $lineTax = (int) round($lineSubtotal * (float) $entry['tax_rate_percent'] / 100);
                $receivedQuantity = (float) $entry['quantity'] + (float) ($entry['free_quantity'] ?? 0);
                $batch = MedicineBatch::where('branch_id', $this->tenant->branchId())->where('medicine_id', $medicine->id)->where('batch_number', trim($entry['batch_number']))->lockForUpdate()->first();
                if ($batch && $batch->expires_on->format('Y-m-d') !== $entry['expires_on']) {
                    throw ValidationException::withMessages(["items.{$index}.batch_number" => 'An existing batch must keep the same expiry date.']);
                }
                if ($batch && $batch->manufactured_on?->format('Y-m-d') !== ($entry['manufactured_on'] ?? null)) {
                    throw ValidationException::withMessages(["items.{$index}.batch_number" => 'An existing batch must keep the same manufacture date.']);
                }
                if (! $batch) {
                    $batch = MedicineBatch::create(['hospital_id' => $this->tenant->hospitalId(), 'branch_id' => $this->tenant->branchId(), 'medicine_id' => $medicine->id, 'batch_number' => trim($entry['batch_number']), 'manufactured_on' => $entry['manufactured_on'] ?? null, 'expires_on' => $entry['expires_on'], 'received_quantity' => '0.000', 'on_hand_quantity' => '0.000', 'unit_cost' => $entry['unit_cost'], 'sale_price' => $entry['sale_price'], 'status' => 'active']);
                }
                $balance = (float) $batch->on_hand_quantity + $receivedQuantity;
                $batch->update(['received_quantity' => $this->quantity((float) $batch->received_quantity + $receivedQuantity), 'on_hand_quantity' => $this->quantity($balance), 'unit_cost' => $entry['unit_cost'], 'sale_price' => $entry['sale_price']]);
                $item = $purchase->items()->create(['medicine_id' => $medicine->id, 'medicine_batch_id' => $batch->id, 'medicine_code' => $medicine->code, 'medicine_name' => $medicine->name, 'batch_number' => $batch->batch_number, 'manufactured_on' => $entry['manufactured_on'] ?? null, 'expires_on' => $entry['expires_on'], 'unit_symbol' => $medicine->purchaseUnit?->symbol, 'quantity' => $entry['quantity'], 'free_quantity' => $entry['free_quantity'] ?? 0, 'unit_cost' => $entry['unit_cost'], 'sale_price' => $entry['sale_price'], 'tax_rate_percent' => $entry['tax_rate_percent'], 'subtotal' => $this->money($lineSubtotal), 'tax_amount' => $this->money($lineTax), 'total' => $this->money($lineSubtotal + $lineTax)]);
                $item->batch->movements()->create(['hospital_id' => $this->tenant->hospitalId(), 'branch_id' => $this->tenant->branchId(), 'medicine_id' => $medicine->id, 'pharmacy_purchase_item_id' => $item->id, 'type' => 'RECEIPT', 'quantity' => $this->quantity($receivedQuantity), 'balance_after' => $this->quantity($balance), 'reference_type' => PharmacyPurchase::class, 'reference_id' => $purchase->id, 'recorded_by' => auth()->id(), 'recorded_at' => now()]);
                $subtotal += $lineSubtotal;
                $tax += $lineTax;
            }
            $purchase->subtotal = $this->money($subtotal);
            $purchase->tax_amount = $this->money($tax);
            $purchase->total = $this->money($subtotal + $tax);
            $purchase->saveQuietly();
            $this->audit->record('pharmacy_purchases', 'received', $purchase, null, $purchase->toArray());

            return $purchase->load($this->relations());
        }, 3);
    }

    /** @return array<int, string> */
    public function relations(): array
    {
        return ['supplier', 'receivedBy:id,name', 'items.batch.movements.recordedBy:id,name'];
    }

    private function cents(string $value): int
    {
        return (int) round((float) $value * 100);
    }

    private function money(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    private function quantity(float $value): string
    {
        return number_format($value, 3, '.', '');
    }
}
