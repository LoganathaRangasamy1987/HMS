<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Hospital;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\ServiceItem;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(private TenantContext $tenant, private ServicePricing $pricing, private AuditService $audit) {}

    /** @param array<string, mixed> $data */
    public function saveDraft(array $data, ?Invoice $invoice = null): Invoice
    {
        return DB::transaction(function () use ($data, $invoice): Invoice {
            if ($invoice) {
                $invoice = Invoice::whereKey($invoice->id)->where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())->lockForUpdate()->firstOrFail();
                if ($invoice->status !== 'DRAFT') {
                    throw ValidationException::withMessages(['invoice' => 'Only draft invoices can be edited.']);
                }
            }
            $branch = $this->tenant->branch();
            $patient = Patient::forHospital($this->tenant->hospitalId())->where('status', 'active')->findOrFail($data['patient_id']);
            $appointment = isset($data['appointment_id']) ? Appointment::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $branch->id)->where('patient_id', $patient->id)->findOrFail($data['appointment_id']) : null;
            if ($appointment && in_array($appointment->status, ['CANCELLED', 'NO_SHOW'], true)) {
                throw ValidationException::withMessages(['appointment_id' => 'Cancelled and no-show appointments cannot be invoiced.']);
            }
            if ($appointment && Invoice::where('appointment_id', $appointment->id)->when($invoice, fn ($query) => $query->whereKeyNot($invoice->id))->exists()) {
                throw ValidationException::withMessages(['appointment_id' => 'This appointment already has an invoice.']);
            }
            $subtotal = $discount = $tax = $total = 0;
            $lines = [];
            foreach ($data['lines'] as $entry) {
                $service = ServiceItem::where('hospital_id', $this->tenant->hospitalId())->findOrFail($entry['service_item_id']);
                $quote = $this->pricing->quote($service, $branch);
                $quantity = (int) $entry['quantity'];
                $unitSubtotal = $this->cents($quote['base_price']);
                $unitDiscount = $this->cents($quote['discount_amount']);
                $unitTax = $this->cents($quote['tax_amount']);
                $unitTotal = $this->cents($quote['total']);
                $subtotal += $unitSubtotal * $quantity;
                $discount += $unitDiscount * $quantity;
                $tax += $unitTax * $quantity;
                $total += $unitTotal * $quantity;
                $lines[] = [
                    'service_item_id' => $service->id, 'service_code' => $service->code, 'description' => $service->name,
                    'quantity' => $quantity, 'unit_price' => $quote['base_price'], 'discount_type' => $quote['discount_type'],
                    'discount_value' => $quote['discount_value'], 'tax_rate_percent' => $quote['tax_rate_percent'],
                    'subtotal' => $this->money($unitSubtotal * $quantity), 'discount_amount' => $this->money($unitDiscount * $quantity),
                    'tax_amount' => $this->money($unitTax * $quantity), 'total' => $this->money($unitTotal * $quantity),
                ];
            }
            if ($total > 99999999999999) {
                throw ValidationException::withMessages(['lines' => 'Invoice total is too large.']);
            }
            $created = $invoice === null;
            $invoice ??= Invoice::create(['hospital_id' => $this->tenant->hospitalId(), 'branch_id' => $branch->id, 'patient_id' => $patient->id, 'appointment_id' => $appointment?->id, 'created_by' => auth()->id()]);
            $invoice->patient_id = $patient->id;
            $invoice->appointment_id = $appointment?->id;
            $invoice->subtotal = $this->money($subtotal);
            $invoice->discount_total = $this->money($discount);
            $invoice->tax_total = $this->money($tax);
            $invoice->total = $this->money($total);
            $invoice->saveOrFail();
            $invoice->lines()->each(fn ($line) => $line->delete());
            $invoice->lines()->createMany($lines);
            $this->audit->record('invoices', $created ? 'created' : 'updated', $invoice, null, $invoice->toArray());

            return $invoice->load(['lines', 'patient', 'appointment']);
        }, 3);
    }

    public function issue(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $invoice = Invoice::whereKey($invoice->id)->where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())->lockForUpdate()->firstOrFail();
            if ($invoice->status !== 'DRAFT' || ! $invoice->lines()->exists()) {
                throw ValidationException::withMessages(['invoice' => 'Only a draft with line items can be issued.']);
            }
            if ($invoice->appointment_id) {
                $appointment = Appointment::where('hospital_id', $invoice->hospital_id)->where('branch_id', $invoice->branch_id)->where('patient_id', $invoice->patient_id)->findOrFail($invoice->appointment_id);
                if (in_array($appointment->status, ['CANCELLED', 'NO_SHOW'], true)) {
                    throw ValidationException::withMessages(['appointment_id' => 'Cancelled and no-show appointments cannot be invoiced.']);
                }
            }
            Hospital::whereKey($invoice->hospital_id)->lockForUpdate()->firstOrFail();
            $year = (int) now('Asia/Kolkata')->format('Y');
            $sequence = DB::table('invoice_number_sequences')->where('hospital_id', $invoice->hospital_id)->where('year', $year)->first();
            $next = ($sequence?->last_number ?? 0) + 1;
            DB::table('invoice_number_sequences')->updateOrInsert(['hospital_id' => $invoice->hospital_id, 'year' => $year], ['last_number' => $next]);
            $invoice->number = sprintf('INV-%d-%d-%06d', $invoice->hospital_id, $year, $next);
            $invoice->status = 'ISSUED';
            $invoice->issued_by = auth()->id();
            $invoice->issued_at = now();
            $invoice->saveOrFail();
            $this->audit->record('invoices', 'issued', $invoice, null, $invoice->toArray());

            return $invoice->load(['lines', 'patient', 'appointment']);
        }, 3);
    }

    private function cents(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function money(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
