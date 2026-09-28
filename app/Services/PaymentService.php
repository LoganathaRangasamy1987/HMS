<?php

namespace App\Services;

use App\Models\Hospital;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit) {}

    /** @param array<string, string> $data
     * @return array{payment: Payment, invoice: Invoice, summary: array<string, string>, replayed: bool}
     */
    public function collect(Invoice $invoice, array $data): array
    {
        return DB::transaction(function () use ($invoice, $data): array {
            $invoice = Invoice::whereKey($invoice->id)->where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())->lockForUpdate()->firstOrFail();
            Hospital::whereKey($invoice->hospital_id)->lockForUpdate()->firstOrFail();
            $amount = $this->cents($data['amount']);
            $reference = $data['reference'] ?? null;
            $existing = Payment::where('hospital_id', $invoice->hospital_id)->where('request_key', $data['request_key'])->first();
            if ($existing) {
                if ($existing->invoice_id !== $invoice->id || $this->cents($existing->amount) !== $amount || $existing->mode !== $data['mode'] || $existing->reference !== $reference) {
                    throw ValidationException::withMessages(['request_key' => 'This request key was already used for a different payment.']);
                }

                return ['payment' => $existing, 'invoice' => $invoice, 'summary' => $this->summary($invoice), 'replayed' => true];
            }
            if (! in_array($invoice->status, ['ISSUED', 'PARTIALLY_PAID'], true)) {
                throw ValidationException::withMessages(['invoice' => 'Payments require an issued invoice with a balance.']);
            }
            $summary = $this->summary($invoice);
            $paid = $this->cents($summary['net_paid']);
            $balance = $this->cents($summary['balance']);
            if ($amount <= 0 || $amount > $balance) {
                throw ValidationException::withMessages(['amount' => 'Payment must be greater than zero and cannot exceed the remaining balance.']);
            }
            $payment = Payment::create([
                'hospital_id' => $invoice->hospital_id, 'branch_id' => $invoice->branch_id, 'invoice_id' => $invoice->id,
                'amount' => $this->money($amount), 'mode' => $data['mode'], 'reference' => $reference,
                'request_key' => $data['request_key'], 'received_at' => now(), 'received_by' => auth()->id(),
            ]);
            $invoice->status = $amount === $balance ? 'PAID' : 'PARTIALLY_PAID';
            $invoice->saveOrFail();
            $this->audit->record('payments', 'recorded', $payment, null, $payment->toArray());
            $this->audit->record('invoices', 'payment_recorded', $invoice, null, $invoice->toArray());

            return ['payment' => $payment, 'invoice' => $invoice, 'summary' => $this->summary($invoice), 'replayed' => false];
        }, 3);
    }

    /** @return array{gross_paid: string, refunded_total: string, net_paid: string, paid_total: string, credit_total: string, debit_total: string, adjusted_total: string, balance: string} */
    public function summary(Invoice $invoice): array
    {
        $grossPaid = $this->paidCents($invoice);
        $refunds = $this->adjustmentCents($invoice, 'REFUND');
        $credits = $this->adjustmentCents($invoice, 'CREDIT');
        $debits = $this->adjustmentCents($invoice, 'DEBIT');
        $netPaid = $grossPaid - $refunds;
        $adjustedTotal = $invoice->status === 'VOID' ? 0 : $this->cents($invoice->total) + $debits - $credits;

        return [
            'gross_paid' => $this->money($grossPaid),
            'refunded_total' => $this->money($refunds),
            'net_paid' => $this->money($netPaid),
            'paid_total' => $this->money($netPaid),
            'credit_total' => $this->money($credits),
            'debit_total' => $this->money($debits),
            'adjusted_total' => $this->money($adjustedTotal),
            'balance' => $this->money(max(0, $adjustedTotal - $netPaid)),
        ];
    }

    public function status(Invoice $invoice): string
    {
        $summary = $this->summary($invoice);
        if ($summary['balance'] === '0.00') {
            return 'PAID';
        }

        return $summary['net_paid'] === '0.00' ? 'ISSUED' : 'PARTIALLY_PAID';
    }

    private function paidCents(Invoice $invoice): int
    {
        $paid = 0;
        foreach ($invoice->payments()->pluck('amount') as $amount) {
            $paid += $this->cents($amount);
        }

        return $paid;
    }

    private function adjustmentCents(Invoice $invoice, string $type): int
    {
        $total = 0;
        foreach ($invoice->financialAdjustments()->where('type', $type)->pluck('amount') as $amount) {
            $total += $this->cents($amount);
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
