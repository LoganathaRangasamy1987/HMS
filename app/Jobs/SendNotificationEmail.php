<?php

namespace App\Jobs;

use App\Mail\OperationalNotificationMail;
use App\Models\NotificationDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendNotificationEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public NotificationDelivery $delivery) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $delivery = $this->delivery->fresh(['notification.recipient']);
        if (! $delivery || $delivery->status === 'SENT') {
            return;
        }
        $delivery->update(['status' => 'PROCESSING', 'attempts' => $delivery->attempts + 1, 'last_attempt_at' => now(), 'last_error' => null]);
        Mail::to($delivery->notification->recipient->email)->send(new OperationalNotificationMail($delivery->notification));
        $delivery->update(['status' => 'SENT', 'sent_at' => now(), 'failed_at' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->delivery->fresh()?->update(['status' => 'FAILED', 'failed_at' => now(), 'last_error' => mb_substr($exception?->getMessage() ?? 'Email delivery failed.', 0, 500)]);
    }
}
