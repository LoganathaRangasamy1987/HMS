<?php

namespace App\Services;

use App\Models\Hospital;
use App\Models\LabOrder;
use App\Models\LabOrderItem;
use App\Models\LabSpecimen;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LabSpecimenService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit, private LabNotificationService $notifications) {}

    public function collect(LabOrderItem $item): LabSpecimen
    {
        return DB::transaction(function () use ($item): LabSpecimen {
            $item = $this->lockedItem($item);
            $order = LabOrder::whereKey($item->lab_order_id)->lockForUpdate()->firstOrFail();
            if ($order->status === 'CANCELLED') {
                throw ValidationException::withMessages(['specimen' => 'Cancelled orders cannot have specimens collected.']);
            }
            $latest = $item->specimens()->lockForUpdate()->latest('attempt')->first();
            if ($latest && $latest->status !== 'REJECTED') {
                throw ValidationException::withMessages(['specimen' => 'Recollection is available only after the previous specimen is rejected.']);
            }
            Hospital::whereKey($this->tenant->hospitalId())->lockForUpdate()->firstOrFail();
            $year = (int) now('Asia/Kolkata')->format('Y');
            $sequence = DB::table('lab_specimen_number_sequences')->where('hospital_id', $this->tenant->hospitalId())->where('year', $year)->first();
            $next = ($sequence?->last_number ?? 0) + 1;
            DB::table('lab_specimen_number_sequences')->updateOrInsert(['hospital_id' => $this->tenant->hospitalId(), 'year' => $year], ['last_number' => $next]);
            $specimen = LabSpecimen::create([
                'hospital_id' => $this->tenant->hospitalId(), 'branch_id' => $this->tenant->branchId(), 'lab_order_id' => $order->id,
                'lab_order_item_id' => $item->id, 'identifier' => sprintf('SPC-%d-%d-%07d', $this->tenant->hospitalId(), $year, $next),
                'attempt' => ($latest?->attempt ?? 0) + 1, 'status' => 'COLLECTED', 'collected_by' => auth()->id(), 'collected_at' => now(),
            ]);
            $this->event($specimen, null, 'COLLECTED');
            $item->update(['status' => 'COLLECTED']);
            $this->syncOrderStatus($order);
            $this->audit->record('lab_specimens', 'collected', $specimen, null, $specimen->toArray());

            return $specimen->load($this->relations());
        }, 3);
    }

    public function receive(LabSpecimen $specimen): LabSpecimen
    {
        return $this->transition($specimen, 'RECEIVED', ['COLLECTED']);
    }

    public function startProcessing(LabSpecimen $specimen): LabSpecimen
    {
        return $this->transition($specimen, 'PROCESSING', ['RECEIVED']);
    }

    public function reject(LabSpecimen $specimen, string $reason): LabSpecimen
    {
        return $this->transition($specimen, 'REJECTED', ['COLLECTED', 'RECEIVED', 'PROCESSING'], $reason);
    }

    /** @param array<int, string> $allowedFrom */
    private function transition(LabSpecimen $specimen, string $toStatus, array $allowedFrom, ?string $reason = null): LabSpecimen
    {
        return DB::transaction(function () use ($specimen, $toStatus, $allowedFrom, $reason): LabSpecimen {
            $specimen = LabSpecimen::whereKey($specimen->id)->where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())->lockForUpdate()->firstOrFail();
            $item = $this->lockedItem($specimen->orderItem);
            $order = LabOrder::whereKey($specimen->lab_order_id)->lockForUpdate()->firstOrFail();
            if (! in_array($specimen->status, $allowedFrom, true)) {
                throw ValidationException::withMessages(['specimen' => "A {$specimen->status} specimen cannot transition to {$toStatus}."]);
            }
            if ($item->specimens()->where('attempt', '>', $specimen->attempt)->exists()) {
                throw ValidationException::withMessages(['specimen' => 'An earlier specimen attempt can no longer be changed.']);
            }
            if ($toStatus === 'REJECTED' && $specimen->results()->exists()) {
                throw ValidationException::withMessages(['specimen' => 'A specimen with result entry cannot be rejected.']);
            }
            $fromStatus = $specimen->status;
            $attributes = ['status' => $toStatus];
            if ($toStatus === 'RECEIVED') {
                $attributes += ['received_by' => auth()->id(), 'received_at' => now()];
            } elseif ($toStatus === 'PROCESSING') {
                $attributes += ['processing_by' => auth()->id(), 'processing_at' => now()];
            } else {
                $attributes += ['rejected_by' => auth()->id(), 'rejected_at' => now(), 'rejection_reason' => $reason];
            }
            $specimen->update($attributes);
            $this->event($specimen, $fromStatus, $toStatus, $reason);
            $item->update(['status' => $toStatus === 'REJECTED' ? 'RECOLLECTION_REQUIRED' : $toStatus]);
            $this->syncOrderStatus($order);
            $this->audit->record('lab_specimens', strtolower($toStatus), $specimen, ['status' => $fromStatus], $specimen->fresh()->toArray());
            if ($toStatus === 'REJECTED') {
                $this->notifications->specimenRejected($specimen);
            }

            return $specimen->load($this->relations());
        }, 3);
    }

    private function lockedItem(LabOrderItem $item): LabOrderItem
    {
        return LabOrderItem::whereKey($item->id)->whereHas('order', fn ($query) => $query->where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId()))->lockForUpdate()->firstOrFail();
    }

    private function event(LabSpecimen $specimen, ?string $from, string $to, ?string $reason = null): void
    {
        $specimen->events()->create(['from_status' => $from, 'to_status' => $to, 'reason' => $reason, 'recorded_by' => auth()->id(), 'recorded_at' => now()]);
    }

    private function syncOrderStatus(LabOrder $order): void
    {
        $statuses = $order->items()->pluck('status');
        $status = match (true) {
            $statuses->contains('RECOLLECTION_REQUIRED') => 'RECOLLECTION_REQUIRED',
            $statuses->every(fn (string $status) => $status === 'PROCESSING') => 'PROCESSING',
            $statuses->every(fn (string $status) => in_array($status, ['RECEIVED', 'PROCESSING'], true)) => 'RECEIVED',
            $statuses->every(fn (string $status) => in_array($status, ['COLLECTED', 'RECEIVED', 'PROCESSING'], true)) => 'COLLECTED',
            default => 'PARTIALLY_COLLECTED',
        };
        $order->update(['status' => $status]);
    }

    /** @return array<int, string> */
    private function relations(): array
    {
        return ['orderItem:id,test_code,test_name', 'events.recordedBy:id,name', 'collectedBy:id,name', 'receivedBy:id,name', 'processingBy:id,name', 'rejectedBy:id,name'];
    }
}
