<?php

namespace App\Services;

use App\Models\FinancialAdjustment;
use App\Models\Hospital;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinancialAdjustmentService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit, private PaymentService $payments) {}

    /** @param array<string, string> $data
     * @return array{adjustment: FinancialAdjustment, invoice: Invoice, summary: array<string, string>, replayed: bool}
     */
    public function adjust(Invoice $invoice, array $data): array
    {
        return $this->record($invoice, $data['type'], $data, null);
    }

    /** @param array<string, string> $data
     * @return array{adjustment: FinancialAdjustment, invoice: Invoice, summary: array<string, string>, replayed: bool}
     */
    public function refund(Invoice $invoice, Payment $payment, array $data): array
    {
        return $this->record($invoice, 'REFUND', $data, $payment);
    }

    /** @param array<string, string> $data
     * @return array{adjustment: FinancialAdjustment, invoice: Invoice, summary: array<string, string>, replayed: bool}
     */
    public function void(Invoice $invoice, array $data): array
    {
        $data['amount'] = $invoice->total;

        return $this->record($invoice, 'VOID', $data, null);
    }

    /** @param array<string, string> $data
     * @return array{adjustment: FinancialAdjustment, invoice: Invoice, summary: array<string, string>, replayed: bool}
     */
    private function record(Invoice $invoice, string $type, array $data, ?Payment $payment): array
    {
        return DB::transaction(function () use ($invoice, $type, $data, $payment): array {
            $invoice = Invoice::whereKey($invoice->id)->where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())->lockForUpdate()->firstOrFail();
            Hospital::whereKey($invoice->hospital_id)->lockForUpdate()->firstOrFail();
            $amount = $this->cents($data['amount']);
            $existing = FinancialAdjustment::where('hospital_id', $invoice->hospital_id)->where('request_key', $data['request_key'])->first();
            if ($existing) {
                if ($existing->invoice_id !== $invoice->id || $existing->payment_id !== $payment?->id || $existing->type !== $type || $this->cents($existing->amount) !== $amount || $existing->reason !== $data['reason']) {
                    throw ValidationException::withMessages(['request_key' => 'This request key was already used for a different financial action.']);
                }

                return ['adjustment' => $existing, 'invoice' => $invoice, 'summary' => $this->payments->summary($invoice), 'replayed' => true];
            }
            if ($invoice->status === 'DRAFT' || $invoice->status === 'VOID') {
                throw ValidationException::withMessages(['invoice' => 'Financial actions require an active issued invoice.']);
            }
            if ($type === 'VOID' && $invoice->labOrder()->where('status', 'ORDERED')->exists()) {
                throw ValidationException::withMessages(['invoice' => 'Cancel the active laboratory order instead of voiding its invoice directly.']);
            }
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
            }
            $summary = $this->payments->summary($invoice);
            if ($type === 'VOID' && ($summary['gross_paid'] !== '0.00' || $invoice->financialAdjustments()->exists())) {
                throw ValidationException::withMessages(['invoice' => 'Only an invoice with no payments or prior financial actions can be voided.']);
            }
            if ($type === 'REFUND') {
                $payment = Payment::whereKey($payment?->id)->where('invoice_id', $invoice->id)->lockForUpdate()->firstOrFail();
                $refunded = $this->sumCents($payment->refunds()->pluck('amount'));
                if ($amount > $this->cents($payment->amount) - $refunded) {
                    throw ValidationException::withMessages(['amount' => 'Refund cannot exceed the unrefunded amount of this payment.']);
                }
            }
            if ($type === 'CREDIT' && $amount > $this->cents($summary['balance'])) {
                throw ValidationException::withMessages(['amount' => 'Credit adjustment cannot exceed the current outstanding balance.']);
            }
            $adjustment = FinancialAdjustment::create([
                'hospital_id' => $invoice->hospital_id,
                'branch_id' => $invoice->branch_id,
                'invoice_id' => $invoice->id,
                'payment_id' => $payment?->id,
                'type' => $type,
                'amount' => $this->money($amount),
                'reason' => $data['reason'],
                'request_key' => $data['request_key'],
                'recorded_at' => now(),
                'recorded_by' => auth()->id(),
            ]);
            $invoice->status = $type === 'VOID' ? 'VOID' : $this->payments->status($invoice);
            $invoice->saveOrFail();
            $this->audit->record('financial_adjustments', strtolower($type), $adjustment, null, $adjustment->toArray());
            $this->audit->record('invoices', strtolower($type), $invoice, null, $invoice->toArray());

            return ['adjustment' => $adjustment, 'invoice' => $invoice, 'summary' => $this->payments->summary($invoice), 'replayed' => false];
        }, 3);
    }

    /** @param iterable<int, mixed> $amounts */
    private function sumCents(iterable $amounts): int
    {
        $total = 0;
        foreach ($amounts as $amount) {
            $total += $this->cents((string) $amount);
        }

        return $total;
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
