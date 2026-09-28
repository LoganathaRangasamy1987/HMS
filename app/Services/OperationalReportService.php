<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Encounter;
use App\Models\FinancialAdjustment;
use App\Models\Invoice;
use App\Models\LabOrder;
use App\Models\LabResult;
use App\Models\Payment;
use App\Models\PharmacySale;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class OperationalReportService
{
    /** @return array<string, mixed> */
    public function report(int $hospitalId, int $branchId, string $from, string $to, string $timezone): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $from, $timezone)->startOfDay()->utc();
        $end = CarbonImmutable::createFromFormat('!Y-m-d', $to, $timezone)->addDay()->startOfDay()->utc();
        $appointments = Appointment::query()->where('hospital_id', $hospitalId)->where('branch_id', $branchId)->whereDate('appointment_date', '>=', $from)->whereDate('appointment_date', '<=', $to);
        $encounters = Encounter::query()->where('encounters.hospital_id', $hospitalId)->where('encounters.branch_id', $branchId)->where('encounters.opened_at', '>=', $start)->where('encounters.opened_at', '<', $end);
        $invoices = Invoice::query()->where('hospital_id', $hospitalId)->where('branch_id', $branchId)->whereNotIn('status', ['DRAFT', 'VOID'])->where('issued_at', '>=', $start)->where('issued_at', '<', $end)->with(['payments:id,invoice_id,amount', 'financialAdjustments:id,invoice_id,type,amount'])->get();
        $payments = Payment::query()->where('hospital_id', $hospitalId)->where('branch_id', $branchId)->where('received_at', '>=', $start)->where('received_at', '<', $end);
        $refunds = FinancialAdjustment::query()->where('hospital_id', $hospitalId)->where('branch_id', $branchId)->where('type', 'REFUND')->where('recorded_at', '>=', $start)->where('recorded_at', '<', $end);
        $labOrders = LabOrder::query()->where('hospital_id', $hospitalId)->where('branch_id', $branchId)->where('ordered_at', '>=', $start)->where('ordered_at', '<', $end);
        $labResults = LabResult::query()->where('hospital_id', $hospitalId)->where('branch_id', $branchId)->where('entered_at', '>=', $start)->where('entered_at', '<', $end);
        $sales = PharmacySale::query()->where('hospital_id', $hospitalId)->where('branch_id', $branchId)->where('dispensed_at', '>=', $start)->where('dispensed_at', '<', $end);
        $movements = StockMovement::query()->where('hospital_id', $hospitalId)->where('branch_id', $branchId)->where('recorded_at', '>=', $start)->where('recorded_at', '<', $end);
        $revenueCents = $invoices->sum(fn (Invoice $invoice): int => $this->cents($invoice->total));
        $receivableCents = $invoices->sum(function (Invoice $invoice): int {
            $paid = $invoice->payments->sum(fn ($payment): int => $this->cents($payment->amount));
            $refunds = $invoice->financialAdjustments->where('type', 'REFUND')->sum(fn ($adjustment): int => $this->cents($adjustment->amount));
            $credits = $invoice->financialAdjustments->where('type', 'CREDIT')->sum(fn ($adjustment): int => $this->cents($adjustment->amount));
            $debits = $invoice->financialAdjustments->where('type', 'DEBIT')->sum(fn ($adjustment): int => $this->cents($adjustment->amount));

            return max(0, $this->cents($invoice->total) + $debits - $credits - ($paid - $refunds));
        });
        $collectedCents = $this->cents((string) ((clone $payments)->sum('amount') ?: '0'));
        $refundedCents = $this->cents((string) ((clone $refunds)->sum('amount') ?: '0'));

        return [
            'filters' => ['from' => $from, 'to' => $to, 'timezone' => $timezone, 'branch_id' => $branchId],
            'appointments' => ['total' => (clone $appointments)->count(), 'by_status' => $this->countsBy($appointments, 'status')],
            'consultations' => ['total' => (clone $encounters)->count(), 'finalized' => (clone $encounters)->whereHas('consultation', fn (Builder $query) => $query->where('status', 'FINALIZED'))->count()],
            'finance' => ['revenue' => $this->money($revenueCents), 'collected' => $this->money($collectedCents), 'refunded' => $this->money($refundedCents), 'net_collected' => $this->money($collectedCents - $refundedCents), 'receivables' => $this->money($receivableCents), 'invoice_count' => $invoices->count()],
            'departments' => (clone $encounters)->join('departments', 'departments.id', '=', 'encounters.department_id')->selectRaw('departments.name as name, COUNT(*) as total')->groupBy('departments.id', 'departments.name')->orderByDesc('total')->get()->map(fn ($row) => ['name' => $row->name, 'total' => (int) $row->total])->all(),
            'doctors' => (clone $encounters)->join('doctor_profiles', 'doctor_profiles.id', '=', 'encounters.doctor_profile_id')->join('users', 'users.id', '=', 'doctor_profiles.user_id')->selectRaw('users.name as name, COUNT(*) as total')->groupBy('users.id', 'users.name')->orderByDesc('total')->get()->map(fn ($row) => ['name' => $row->name, 'total' => (int) $row->total])->all(),
            'laboratory' => ['orders' => (clone $labOrders)->count(), 'by_status' => $this->countsBy($labOrders, 'status'), 'results_entered' => (clone $labResults)->count(), 'results_verified' => (clone $labResults)->where('status', 'VERIFIED')->count()],
            'pharmacy' => ['sales' => (clone $sales)->count(), 'sales_total' => $this->money($this->cents((string) ((clone $sales)->sum('total') ?: '0'))), 'movements' => (clone $movements)->count(), 'movement_quantities' => $this->sumsBy($movements, 'type', 'quantity')],
        ];
    }

    /** @return array<string, int> */
    private function countsBy(Builder $query, string $column): array
    {
        return (clone $query)->selectRaw("{$column}, COUNT(*) as total")->groupBy($column)->orderBy($column)->pluck('total', $column)->map(fn ($value): int => (int) $value)->all();
    }

    /** @return array<string, string> */
    private function sumsBy(Builder $query, string $group, string $column): array
    {
        return (clone $query)->selectRaw("{$group}, SUM({$column}) as total")->groupBy($group)->orderBy($group)->pluck('total', $group)->map(fn ($value): string => number_format((float) $value, 3, '.', ''))->all();
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
