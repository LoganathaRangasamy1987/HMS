<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\DoctorProfile;
use App\Models\Hospital;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Membership;
use App\Models\NotificationDelivery;
use App\Models\OperationalNotification;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoleDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_reception_dashboard_shows_branch_queue_finance_and_notification_drilldowns(): void
    {
        $branch = $this->branch('CBE');
        $receptionist = User::where('email', 'reception@lotus.test')->firstOrFail();
        $doctor = DoctorProfile::factory()->create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id]);
        $patient = Patient::factory()->forBranch($branch)->create();
        Appointment::factory()->create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'patient_id' => $patient->id, 'doctor_profile_id' => $doctor->id, 'appointment_date' => now($branch->timezone)->toDateString(), 'status' => 'WAITING', 'created_by' => $receptionist->id]);
        $invoice = Invoice::factory()->create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'patient_id' => $patient->id, 'created_by' => $receptionist->id, 'number' => 'DASH-'.Str::uuid(), 'status' => 'PARTIALLY_PAID', 'total' => '500.00', 'issued_by' => $receptionist->id, 'issued_at' => now()]);
        Payment::factory()->create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'invoice_id' => $invoice->id, 'amount' => '200.00', 'received_by' => $receptionist->id, 'received_at' => now()]);
        OperationalNotification::create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'recipient_user_id' => $receptionist->id, 'event_key' => 'dashboard-reception', 'type' => 'QUEUE', 'title' => 'Queue alert', 'message' => 'Patient waiting.', 'url' => '/appointments']);
        $this->signIn('reception@lotus.test');

        $cards = $this->cards($this->getJson('/api/v1/dashboard')->assertOk()->json('workflowCards'));
        $this->assertSame(1, $cards['appointments_today']['value']);
        $this->assertSame(1, $cards['waiting_patients']['value']);
        $this->assertSame(1, $cards['outstanding_invoices']['value']);
        $this->assertSame('₹200.00', $cards['collections_today']['value']);
        $this->assertSame(1, $cards['unread_notifications']['value']);
        $this->assertArrayHasKey('lab_collection', $cards);
        $this->assertArrayNotHasKey('lab_verification', $cards);
        $this->assertArrayNotHasKey('low_stock', $cards);
        $this->get('/dashboard')->assertOk()->assertSee('Your operational priorities')->assertSee('Waiting patients')->assertSee('Net collections today');
    }

    public function test_doctor_dashboard_limits_appointments_to_the_signed_in_doctor(): void
    {
        $branch = $this->branch('CBE');
        $doctorUser = User::where('email', 'doctor@lotus.test')->firstOrFail();
        $doctor = DoctorProfile::factory()->create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'user_id' => $doctorUser->id]);
        $otherDoctor = DoctorProfile::factory()->create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id]);
        foreach ([$doctor, $otherDoctor] as $profile) {
            Appointment::factory()->create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'doctor_profile_id' => $profile->id, 'appointment_date' => now($branch->timezone)->toDateString(), 'status' => 'WAITING']);
        }
        $this->signIn('doctor@lotus.test');

        $cards = $this->cards($this->getJson('/api/v1/dashboard')->assertOk()->json('workflowCards'));
        $this->assertSame(1, $cards['appointments_today']['value']);
        $this->assertSame(1, $cards['waiting_patients']['value']);
        $this->assertArrayHasKey('lab_collection', $cards);
        $this->assertArrayHasKey('lab_verification', $cards);
        $this->assertArrayNotHasKey('outstanding_invoices', $cards);
        $this->assertArrayNotHasKey('low_stock', $cards);
    }

    public function test_pharmacist_dashboard_shows_stock_alerts_without_other_module_data(): void
    {
        $branch = $this->branch('CBE');
        $pharmacist = User::factory()->create(['hospital_id' => $branch->hospital_id]);
        Membership::create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'user_id' => $pharmacist->id, 'role_id' => Role::where('name', 'PHARMACIST')->firstOrFail()->id, 'status' => 'active']);
        $medicine = Medicine::factory()->create(['hospital_id' => $branch->hospital_id, 'reorder_level' => '10.000']);
        MedicineBatch::factory()->create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'medicine_id' => $medicine->id, 'on_hand_quantity' => '2.000', 'expires_on' => now($branch->timezone)->addDays(5)->toDateString()]);
        $this->signInAs($pharmacist);

        $cards = $this->cards($this->getJson('/api/v1/dashboard')->assertOk()->json('workflowCards'));
        $this->assertGreaterThanOrEqual(1, $cards['low_stock']['value']);
        $this->assertGreaterThanOrEqual(1, $cards['expiring_stock']['value']);
        $this->assertArrayHasKey('unread_notifications', $cards);
        $this->assertArrayNotHasKey('appointments_today', $cards);
        $this->assertArrayNotHasKey('outstanding_invoices', $cards);
        $this->assertArrayNotHasKey('lab_collection', $cards);
    }

    public function test_admin_failure_alerts_and_metrics_are_active_branch_scoped(): void
    {
        $admin = User::where('email', 'admin@lotus.test')->firstOrFail();
        $cbe = $this->branch('CBE');
        $chn = $this->branch('CHN');
        foreach ([[$cbe, 'visible-failure'], [$chn, 'hidden-failure']] as [$branch, $eventKey]) {
            $notification = OperationalNotification::create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'recipient_user_id' => $admin->id, 'event_key' => $eventKey, 'type' => 'DELIVERY', 'title' => $eventKey, 'message' => 'Delivery failed.', 'url' => '/notifications']);
            NotificationDelivery::create(['operational_notification_id' => $notification->id, 'channel' => 'EMAIL', 'status' => 'FAILED', 'attempts' => 3, 'failed_at' => now(), 'last_error' => 'Unavailable']);
        }
        $this->signIn('admin@lotus.test');

        $cards = $this->cards($this->getJson('/api/v1/dashboard')->assertOk()->json('workflowCards'));
        $this->assertSame(1, $cards['failed_deliveries']['value']);
        $this->assertStringContainsString('/notifications', $cards['failed_deliveries']['url']);
    }

    /** @param list<array<string, mixed>> $cards
     * @return array<string, array<string, mixed>>
     */
    private function cards(array $cards): array
    {
        return collect($cards)->keyBy('key')->all();
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

    private function signInAs(User $user): void
    {
        $membership = $user->memberships()->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);
    }
}
