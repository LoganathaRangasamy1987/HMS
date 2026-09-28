<?php

namespace App\Services;

use App\Models\LabNotification;
use App\Models\LabOrder;
use App\Models\LabResult;
use App\Models\LabSpecimen;
use App\Models\Membership;
use Illuminate\Support\Collection;

class LabNotificationService
{
    public function __construct(private NotificationService $notifications) {}

    public function orderCreated(LabOrder $order): void
    {
        $recipients = Membership::query()->active()->where('hospital_id', $order->hospital_id)->where('branch_id', $order->branch_id)
            ->whereHas('role', fn ($query) => $query->where('name', 'LAB_TECHNICIAN'))->pluck('user_id');
        $this->send($order, $recipients, "order:{$order->id}:created", 'ORDER_CREATED', 'New laboratory order', "{$order->number} is ready for specimen collection.", route('laboratory.orders.show', $order, false));
    }

    public function specimenRejected(LabSpecimen $specimen): void
    {
        $specimen->loadMissing(['order.doctorProfile', 'orderItem']);
        $recipients = collect([$specimen->order->doctorProfile->user_id, $specimen->order->ordered_by]);
        $this->send($specimen->order, $recipients, "specimen:{$specimen->id}:rejected", 'SPECIMEN_REJECTED', 'Specimen recollection required', "{$specimen->identifier} for {$specimen->orderItem->test_name} was rejected.", route('laboratory.orders.show', $specimen->order, false));
    }

    public function resultReady(LabResult $result): void
    {
        $result->loadMissing('order.doctorProfile');
        $this->send($result->order, collect([$result->order->doctorProfile->user_id]), "result:{$result->id}:ready", 'RESULT_READY', 'Laboratory result ready for verification', "{$result->order->number} revision {$result->revision} is ready for verification.", route('laboratory.results.show', $result, false));
    }

    public function resultFinalized(LabResult $result): void
    {
        $result->loadMissing('order.doctorProfile');
        $recipients = collect([$result->entered_by, $result->order->doctorProfile->user_id]);
        $this->send($result->order, $recipients, "result:{$result->id}:final", 'RESULT_FINALIZED', 'Laboratory report finalized', "{$result->order->number} revision {$result->revision} is finalized and available.", route('laboratory.results.report', $result, false));
    }

    /** @param Collection<int, int|null> $recipients */
    private function send(LabOrder $order, Collection $recipients, string $eventKey, string $type, string $title, string $message, string $url): void
    {
        foreach ($recipients->filter()->unique() as $userId) {
            if ((int) $userId === (int) auth()->id()) {
                continue;
            }
            LabNotification::firstOrCreate(
                ['recipient_user_id' => $userId, 'event_key' => $eventKey],
                ['hospital_id' => $order->hospital_id, 'branch_id' => $order->branch_id, 'type' => $type, 'title' => $title, 'message' => $message, 'url' => $url],
            );
            $this->notifications->notify($order->hospital_id, $order->branch_id, (int) $userId, 'laboratory:'.$eventKey, $type, $title, $message, $url);
        }
    }
}
