<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\FinancialAdjustment;
use App\Models\Invoice;
use App\Models\LabOrderItem;
use App\Models\LabResult;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\NotificationDelivery;
use App\Models\OperationalNotification;
use App\Models\Payment;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class DashboardMetricsService
{
    public function __construct(private TenantContext $tenant) {}

    /** @return list<array{key: string, label: string, value: int|string, detail: string, url: string, severity: string}> */
    public function cards(int $userId): array
    {
        $hospitalId = $this->tenant->hospitalId();
        $branch = $this->tenant->branch();
        $today = CarbonImmutable::today($branch->timezone);
        $start = $today->utc();
        $end = $today->addDay()->utc();
        $cards = [];

        if ($this->tenant->can('APPOINTMENT.VIEW')) {
            $appointments = Appointment::where('hospital_id', $hospitalId)->where('branch_id', $branch->id)->whereDate('appointment_date', $today->toDateString());
            if ($this->tenant->membership()->role->name === 'DOCTOR') {
                $appointments->whereHas('doctorProfile', fn (Builder $query) => $query->where('user_id', $userId));
            }
            $cards[] = $this->card('appointments_today', 'Appointments today', (clone $appointments)->count(), 'Current branch schedule', route('appointments.index', ['date' => $today->toDateString()]), 'info');
            $cards[] = $this->card('waiting_patients', 'Waiting patients', (clone $appointments)->where('status', 'WAITING')->count(), 'Awaiting consultation', route('appointments.index', ['date' => $today->toDateString(), 'status' => 'WAITING']), 'warning');
        }
        if ($this->tenant->can('INVOICE.MANAGE')) {
            $outstanding = Invoice::where('hospital_id', $hospitalId)->where('branch_id', $branch->id)->whereIn('status', ['ISSUED', 'PARTIALLY_PAID'])->count();
            $collected = $this->cents((string) (Payment::where('hospital_id', $hospitalId)->where('branch_id', $branch->id)->where('received_at', '>=', $start)->where('received_at', '<', $end)->sum('amount') ?: '0'));
            $refunded = $this->cents((string) (FinancialAdjustment::where('hospital_id', $hospitalId)->where('branch_id', $branch->id)->where('type', 'REFUND')->where('recorded_at', '>=', $start)->where('recorded_at', '<', $end)->sum('amount') ?: '0'));
            $cards[] = $this->card('outstanding_invoices', 'Outstanding invoices', $outstanding, 'Issued balances requiring payment', route('invoices.index', ['outstanding' => 1]), 'warning');
            $cards[] = $this->card('collections_today', 'Net collections today', '₹'.$this->money($collected - $refunded), 'Payments less refunds', route('billing.reconciliation', ['date' => $today->toDateString()]), 'success');
        }
        if ($this->tenant->can('LAB_WORKLIST.VIEW')) {
            $pendingCollection = LabOrderItem::whereHas('order', fn (Builder $query) => $query->where('hospital_id', $hospitalId)->where('branch_id', $branch->id)->where('status', '!=', 'CANCELLED'))->whereIn('status', ['ORDERED', 'RECOLLECTION_REQUIRED'])->count();
            $pendingVerification = $this->tenant->can('LAB_RESULT.VERIFY') ? LabResult::where('hospital_id', $hospitalId)->where('branch_id', $branch->id)->where('status', 'DRAFT')->whereHas('values')->count() : 0;
            $cards[] = $this->card('lab_collection', 'Lab collection queue', $pendingCollection, 'Ordered or recollection required', route('laboratory.worklist'), 'warning');
            if ($this->tenant->can('LAB_RESULT.VERIFY')) {
                $cards[] = $this->card('lab_verification', 'Results to verify', $pendingVerification, 'Entered results awaiting verification', route('laboratory.worklist'), 'danger');
            }
        }
        if ($this->tenant->can('PHARMACY_WORKSPACE.VIEW')) {
            $medicines = Medicine::where('hospital_id', $hospitalId)->where('status', 'active')->withSum(['batches as branch_on_hand' => fn (Builder $query) => $query->where('branch_id', $branch->id)], 'on_hand_quantity')->get();
            $lowStock = $medicines->filter(fn (Medicine $medicine) => (float) ($medicine->branch_on_hand ?? 0) <= (float) $medicine->reorder_level)->count();
            $expiring = MedicineBatch::where('hospital_id', $hospitalId)->where('branch_id', $branch->id)->where('on_hand_quantity', '>', 0)->whereDate('expires_on', '<=', $today->addDays($branch->pharmacy_expiry_warning_days)->toDateString())->count();
            $cards[] = $this->card('low_stock', 'Low or out of stock', $lowStock, 'At or below reorder level', route('pharmacy.workspace'), 'danger');
            $cards[] = $this->card('expiring_stock', 'Expiry alerts', $expiring, 'Expired or within warning window', route('pharmacy.workspace'), 'warning');
        }
        $unread = OperationalNotification::where('hospital_id', $hospitalId)->where('branch_id', $branch->id)->where('recipient_user_id', $userId)->whereNull('read_at')->count();
        $cards[] = $this->card('unread_notifications', 'Unread notifications', $unread, 'Workflow alerts assigned to you', route('notifications.index'), 'info');
        if ($this->tenant->isAdmin()) {
            $failed = NotificationDelivery::whereHas('notification', fn (Builder $query) => $query->where('hospital_id', $hospitalId)->where('branch_id', $branch->id))->where('status', 'FAILED')->count();
            $cards[] = $this->card('failed_deliveries', 'Failed email deliveries', $failed, 'Requires review or retry', route('notifications.index'), 'danger');
        }

        return $cards;
    }

    /** @return array{key: string, label: string, value: int|string, detail: string, url: string, severity: string} */
    private function card(string $key, string $label, int|string $value, string $detail, string $url, string $severity): array
    {
        return compact('key', 'label', 'value', 'detail', 'url', 'severity');
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
