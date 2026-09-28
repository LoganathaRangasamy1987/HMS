<?php

namespace App\Services;

use App\Jobs\SendNotificationEmail;
use App\Models\NotificationDelivery;
use App\Models\OperationalNotification;
use App\Models\User;

class NotificationService
{
    public function notify(int $hospitalId, int $branchId, int $recipientUserId, string $eventKey, string $type, string $title, string $message, ?string $url = null): OperationalNotification
    {
        $notification = OperationalNotification::firstOrCreate(
            ['recipient_user_id' => $recipientUserId, 'event_key' => $eventKey],
            ['hospital_id' => $hospitalId, 'branch_id' => $branchId, 'type' => $type, 'title' => $title, 'message' => $message, 'url' => $url],
        );
        $recipient = User::whereKey($recipientUserId)->where('hospital_id', $hospitalId)->where('status', 'active')->first();
        if ($notification->wasRecentlyCreated && $recipient?->email) {
            $delivery = NotificationDelivery::create(['operational_notification_id' => $notification->id, 'channel' => 'EMAIL', 'status' => 'QUEUED']);
            SendNotificationEmail::dispatch($delivery)->afterCommit();
        }

        return $notification;
    }
}
