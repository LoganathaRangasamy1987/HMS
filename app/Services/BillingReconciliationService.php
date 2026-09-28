<?php

namespace App\Services;

use App\Models\FinancialAdjustment;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class BillingReconciliationService
{
    /** @return array{date: string, timezone: string, gross_collected: string, refunded: string, net_collected: string, payment_count: int, refund_count: int, modes: array<string, array{count: int, total: string}>, payments: Collection<int, Payment>, refunds: Collection<int, FinancialAdjustment>} */
    public function daily(int $hospitalId, int $branchId, string $date, string $timezone): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone)->startOfDay()->utc();
        $end = $start->setTimezone($timezone)->addDay()->utc();
        $paymentsQuery = Payment::query()
            ->where('hospital_id', $hospitalId)
            ->where('branch_id', $branchId)
            ->where('received_at', '>=', $start)
            ->where('received_at', '<', $end);
        $refundsQuery = FinancialAdjustment::query()
            ->where('hospital_id', $hospitalId)
            ->where('branch_id', $branchId)
            ->where('type', 'REFUND')
            ->where('recorded_at', '>=', $start)
            ->where('recorded_at', '<', $end);

        $modes = [];
        $grossCents = 0;
        foreach ((clone $paymentsQuery)->selectRaw('mode, COUNT(*) as entry_count, SUM(amount) as total')->groupBy('mode')->orderBy('mode')->get() as $mode) {
            $cents = $this->cents((string) $mode->total);
            $grossCents += $cents;
            $modes[$mode->mode] = ['count' => (int) $mode->entry_count, 'total' => $this->money($cents)];
        }
        $refundedCents = $this->cents((string) ((clone $refundsQuery)->sum('amount') ?: '0'));

        return [
            'date' => $date,
            'timezone' => $timezone,
            'gross_collected' => $this->money($grossCents),
            'refunded' => $this->money($refundedCents),
            'net_collected' => $this->money($grossCents - $refundedCents),
            'payment_count' => (clone $paymentsQuery)->count(),
            'refund_count' => (clone $refundsQuery)->count(),
            'modes' => $modes,
            'payments' => $paymentsQuery->with('invoice.patient:id,uhid,first_name,last_name')->latest('received_at')->get(),
            'refunds' => $refundsQuery->with(['invoice.patient:id,uhid,first_name,last_name', 'payment:id,mode,reference'])->latest('recorded_at')->get(),
        ];
    }

    private function cents(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function money(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return sprintf('%s%d.%02d', $sign, intdiv($absolute, 100), $absolute % 100);
    }
}
