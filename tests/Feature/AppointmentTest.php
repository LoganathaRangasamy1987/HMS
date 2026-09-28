<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Department;
use App\Models\DoctorProfile;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_reception_books_capacity_and_tokens_without_overbooking(): void
    {
        $this->signIn('reception@lotus.test');
        $profile = $this->profile(2);
        $first = $this->postJson('/api/v1/appointments', $this->payload($profile, '550e8400-e29b-41d4-a716-446655440000'))->assertCreated()->assertJsonPath('data.token_number', 1);
        $this->postJson('/api/v1/appointments', $this->payload($profile, '550e8400-e29b-41d4-a716-446655440001'))->assertCreated()->assertJsonPath('data.token_number', 2);
        $this->postJson('/api/v1/appointments', $this->payload($profile, '550e8400-e29b-41d4-a716-446655440002'))->assertUnprocessable()->assertJsonValidationErrors('starts_at');
        $this->assertDatabaseCount('appointments', 2);
        $this->assertNotNull($first->json('data.id'));
    }

    public function test_duplicate_request_returns_original_appointment(): void
    {
        $this->signIn('reception@lotus.test');
        $profile = $this->profile();
        $payload = $this->payload($profile, '550e8400-e29b-41d4-a716-446655440003');
        $id = $this->postJson('/api/v1/appointments', $payload)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/appointments', $payload)->assertOk()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_booking_rejects_off_schedule_closure_and_invalid_slot_boundary(): void
    {
        $this->signIn('reception@lotus.test');
        $profile = $this->profile();
        $this->postJson('/api/v1/appointments', [...$this->payload($profile), 'starts_at' => '09:10'])->assertUnprocessable();
        $profile->unavailabilities()->create(['type' => 'leave', 'starts_on' => '2026-10-05', 'ends_on' => '2026-10-05', 'reason' => 'Leave']);
        $this->postJson('/api/v1/appointments', $this->payload($profile))->assertUnprocessable()->assertJsonValidationErrors('appointment_date');
    }

    public function test_reception_reschedules_and_cancels_with_audit(): void
    {
        $this->signIn('reception@lotus.test');
        $profile = $this->profile();
        $id = $this->postJson('/api/v1/appointments', $this->payload($profile))->json('data.id');
        $this->putJson("/api/v1/appointments/{$id}/reschedule", ['doctor_profile_id' => $profile->id, 'appointment_date' => '2026-10-06', 'starts_at' => '09:15'])->assertOk()->assertJsonPath('data.starts_at', '09:15');
        $this->putJson("/api/v1/appointments/{$id}/cancel", ['cancellation_reason' => 'Patient requested'])->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->putJson("/api/v1/appointments/{$id}/cancel", ['cancellation_reason' => 'Again'])->assertUnprocessable();
        $this->assertDatabaseHas('appointments', ['id' => $id, 'status' => 'CANCELLED', 'cancellation_reason' => 'Patient requested']);
        $this->assertDatabaseCount('audit_logs', 7);
    }

    public function test_doctor_sees_only_own_queue_and_cannot_mutate(): void
    {
        $profile = $this->profile();
        $this->signIn('reception@lotus.test');
        $this->postJson('/api/v1/appointments', $this->payload($profile))->assertCreated();
        $this->signIn('doctor@lotus.test');
        $this->getJson('/api/v1/appointments')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/appointments', [])->assertForbidden();
    }

    public function test_reception_check_in_and_doctor_queue_transitions_follow_the_state_machine(): void
    {
        $profile = $this->profile();
        $this->signIn('reception@lotus.test');
        $id = $this->postJson('/api/v1/appointments', [...$this->payload($profile), 'type' => 'WALK_IN'])->assertCreated()->assertJsonPath('data.type', 'WALK_IN')->json('data.id');
        foreach (['CONFIRMED', 'CHECKED_IN', 'WAITING'] as $status) {
            $this->putJson("/api/v1/appointments/{$id}/status", compact('status'))->assertOk()->assertJsonPath('data.status', $status);
        }
        $this->putJson("/api/v1/appointments/{$id}/status", ['status' => 'COMPLETED'])->assertUnprocessable();
        $this->signIn('doctor@lotus.test');
        $this->putJson("/api/v1/appointments/{$id}/status", ['status' => 'CONSULTING'])->assertOk();
        $this->putJson("/api/v1/appointments/{$id}/status", ['status' => 'COMPLETED'])->assertOk();
        $this->putJson("/api/v1/appointments/{$id}/status", ['status' => 'CONSULTING'])->assertUnprocessable();
        $this->assertDatabaseHas('appointments', ['id' => $id, 'status' => 'COMPLETED']);
    }

    public function test_daily_queue_filters_by_doctor_department_and_rejects_future_no_show(): void
    {
        $profile = $this->profile();
        $this->signIn('reception@lotus.test');
        $id = $this->postJson('/api/v1/appointments', $this->payload($profile))->json('data.id');
        $this->getJson('/api/v1/appointments?date=2026-10-05&doctor_profile_id='.$profile->id.'&department_id='.$profile->department_id)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/appointments?date=2026-10-06')->assertOk()->assertJsonCount(0, 'data');
        $this->putJson("/api/v1/appointments/{$id}/status", ['status' => 'NO_SHOW'])->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    private function profile(int $capacity = 1): DoctorProfile
    {
        $hospital = Hospital::where('code', 'LOTUS')->firstOrFail();
        $branch = Branch::where('hospital_id', $hospital->id)->where('code', 'CBE')->firstOrFail();
        $doctor = User::where('email', 'doctor@lotus.test')->firstOrFail();
        $profile = DoctorProfile::firstOrCreate(['hospital_id' => $hospital->id, 'branch_id' => $branch->id, 'user_id' => $doctor->id], ['department_id' => Department::where('branch_id', $branch->id)->value('id'), 'registration_number' => 'APT-DOCTOR', 'qualification' => 'MBBS', 'specialization' => 'General Medicine', 'consultation_fee' => 500, 'status' => 'active']);
        $profile->schedules()->updateOrCreate(['day_of_week' => 1, 'starts_at' => '09:00'], ['ends_at' => '12:00', 'slot_duration_minutes' => 15, 'capacity_per_slot' => $capacity, 'status' => 'active']);
        $profile->schedules()->updateOrCreate(['day_of_week' => 2, 'starts_at' => '09:00'], ['ends_at' => '12:00', 'slot_duration_minutes' => 15, 'capacity_per_slot' => $capacity, 'status' => 'active']);

        return $profile->fresh('branch');
    }

    private function payload(DoctorProfile $profile, ?string $requestKey = null): array
    {
        return ['patient_id' => Patient::where('hospital_id', $profile->hospital_id)->value('id'), 'doctor_profile_id' => $profile->id, 'appointment_date' => '2026-10-05', 'starts_at' => '09:00', 'type' => 'NEW', 'reason' => 'Consultation', 'request_key' => $requestKey];
    }

    private function signIn(string $email): void
    {
        $user = User::where('email', $email)->firstOrFail();
        $membership = $user->memberships()->whereHas('branch', fn ($query) => $query->where('code', 'CBE'))->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);
    }
}
