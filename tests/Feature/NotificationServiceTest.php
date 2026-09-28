<?php

namespace Tests\Feature;

use App\Jobs\SendNotificationEmail;
use App\Mail\OperationalNotificationMail;
use App\Models\Branch;
use App\Models\Hospital;
use App\Models\OperationalNotification;
use App\Models\User;
use App\Services\NotificationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_notification_creation_is_deduplicated_and_queues_email(): void
    {
        Queue::fake();
        $branch = $this->branch('CBE');
        $recipient = User::where('email', 'reception@lotus.test')->firstOrFail();
        $service = app(NotificationService::class);

        $first = $service->notify($branch->hospital_id, $branch->id, $recipient->id, 'appointment:42:booked', 'APPOINTMENT_BOOKED', 'Appointment booked', 'A patient was added to the queue.', '/appointments');
        $second = $service->notify($branch->hospital_id, $branch->id, $recipient->id, 'appointment:42:booked', 'APPOINTMENT_BOOKED', 'Appointment booked', 'A patient was added to the queue.', '/appointments');

        $this->assertTrue($first->is($second));
        $this->assertDatabaseCount('operational_notifications', 1);
        $this->assertDatabaseHas('notification_deliveries', ['operational_notification_id' => $first->id, 'channel' => 'EMAIL', 'status' => 'QUEUED']);
        Queue::assertPushed(SendNotificationEmail::class, 1);
    }

    public function test_email_job_records_attempt_and_success(): void
    {
        Queue::fake();
        Mail::fake();
        $branch = $this->branch('CBE');
        $recipient = User::where('email', 'reception@lotus.test')->firstOrFail();
        $notification = app(NotificationService::class)->notify($branch->hospital_id, $branch->id, $recipient->id, 'billing:7:paid', 'PAYMENT_RECEIVED', 'Payment received', 'The invoice was paid.', '/invoices/7');
        $delivery = $notification->deliveries()->firstOrFail();

        (new SendNotificationEmail($delivery))->handle();

        Mail::assertSent(OperationalNotificationMail::class, fn ($mail) => $mail->hasTo($recipient->email) && $mail->notification->is($notification));
        $this->assertDatabaseHas('notification_deliveries', ['id' => $delivery->id, 'status' => 'SENT', 'attempts' => 1]);
        $this->assertNotNull($delivery->fresh()->sent_at);
    }

    public function test_recipient_read_scope_and_admin_failure_retry_are_enforced(): void
    {
        Queue::fake();
        $branch = $this->branch('CBE');
        $admin = User::where('email', 'admin@lotus.test')->firstOrFail();
        $notification = app(NotificationService::class)->notify($branch->hospital_id, $branch->id, $admin->id, 'lab:9:failed', 'LAB_DELIVERY', 'Laboratory delivery', 'Email delivery requires attention.', '/laboratory/worklist');
        $delivery = $notification->deliveries()->firstOrFail();
        (new SendNotificationEmail($delivery))->failed(new RuntimeException('SMTP unavailable'));
        $this->signIn('admin@lotus.test');

        $this->get('/notifications')->assertOk()->assertSee('Laboratory delivery')->assertSee('SMTP unavailable');
        $this->putJson("/api/v1/notifications/{$notification->id}/read")->assertOk()->assertJsonPath('data.read_at', fn ($value) => $value !== null);
        $this->postJson("/api/v1/notifications/deliveries/{$delivery->id}/retry")->assertOk()->assertJsonPath('data.status', 'QUEUED');
        Queue::assertPushed(SendNotificationEmail::class, 2);

        $this->signIn('reception@lotus.test');
        $this->putJson("/api/v1/notifications/{$notification->id}/read")->assertNotFound();
        $delivery->update(['status' => 'FAILED']);
        $this->postJson("/api/v1/notifications/deliveries/{$delivery->id}/retry")->assertForbidden();
    }

    public function test_notification_center_is_branch_and_hospital_scoped(): void
    {
        Queue::fake();
        $admin = User::where('email', 'admin@lotus.test')->firstOrFail();
        $cbe = $this->branch('CBE');
        $chn = $this->branch('CHN');
        OperationalNotification::create(['hospital_id' => $cbe->hospital_id, 'branch_id' => $cbe->id, 'recipient_user_id' => $admin->id, 'event_key' => 'visible', 'type' => 'TEST', 'title' => 'Visible branch alert', 'message' => 'Visible', 'url' => '/dashboard']);
        OperationalNotification::create(['hospital_id' => $chn->hospital_id, 'branch_id' => $chn->id, 'recipient_user_id' => $admin->id, 'event_key' => 'hidden', 'type' => 'TEST', 'title' => 'Hidden branch alert', 'message' => 'Hidden', 'url' => '/dashboard']);
        $this->signIn('admin@lotus.test');

        $this->get('/notifications')->assertOk()->assertSee('Visible branch alert')->assertDontSee('Hidden branch alert');
        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('unread_count', 1)->assertJsonCount(1, 'data.notifications.data');
    }

    private function branch(string $code): Branch
    {
        return Branch::where('hospital_id', Hospital::where('code', 'LOTUS')->value('id'))->where('code', $code)->firstOrFail();
    }

    private function signIn(string $email, string $branchCode = 'CBE'): void
    {
        $user = User::where('email', $email)->firstOrFail();
        $membership = $user->memberships()->whereHas('branch', fn ($query) => $query->where('code', $branchCode))->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);
    }
}
