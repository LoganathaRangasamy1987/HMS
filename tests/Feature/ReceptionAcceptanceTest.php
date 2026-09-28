<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\DoctorProfile;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceptionAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_reception_registers_books_checks_in_and_assigned_doctor_receives_the_patient(): void
    {
        $this->signIn('reception@lotus.test');
        $patientId = $this->postJson('/api/v1/patients', [
            'first_name' => 'Acceptance', 'last_name' => 'Patient', 'date_of_birth_unknown' => true,
            'gender' => 'unknown', 'mobile' => '9876543210',
        ])->assertCreated()->json('data.id');
        $profile = $this->profile();
        $appointmentId = $this->postJson('/api/v1/appointments', [
            'patient_id' => $patientId, 'doctor_profile_id' => $profile->id,
            'appointment_date' => '2026-10-05', 'starts_at' => '09:00', 'type' => 'WALK_IN',
        ])->assertCreated()->assertJsonPath('data.token_number', 1)->json('data.id');

        $this->putJson("/api/v1/appointments/{$appointmentId}/status", ['status' => 'CHECKED_IN'])->assertOk();
        $this->putJson("/api/v1/appointments/{$appointmentId}/status", ['status' => 'WAITING'])->assertOk();
        $this->signIn('doctor@lotus.test');
        $this->getJson('/api/v1/appointments?date=2026-10-05&status=WAITING')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $appointmentId);
        $this->putJson("/api/v1/appointments/{$appointmentId}/status", ['status' => 'CONSULTING'])->assertOk();
        $this->assertDatabaseHas('appointments', ['id' => $appointmentId, 'patient_id' => $patientId, 'status' => 'CONSULTING']);
    }

    public function test_other_hospital_and_other_branch_records_are_inaccessible(): void
    {
        $this->signIn('reception@lotus.test');
        $profile = $this->profile();
        $patient = Patient::where('hospital_id', $profile->hospital_id)->firstOrFail();
        $foreign = Patient::where('hospital_id', '!=', $profile->hospital_id)->firstOrFail();
        $payload = ['patient_id' => $foreign->id, 'doctor_profile_id' => $profile->id, 'appointment_date' => '2026-10-05', 'starts_at' => '09:00', 'type' => 'NEW'];
        $this->postJson('/api/v1/appointments', $payload)->assertNotFound();
        $this->postJson('/api/v1/appointments', [...$payload, 'patient_id' => $patient->id, 'doctor_profile_id' => 999999])->assertNotFound();

        $appointmentId = $this->postJson('/api/v1/appointments', [...$payload, 'patient_id' => $patient->id])->assertCreated()->json('data.id');
        $river = Hospital::where('code', 'RIVER')->firstOrFail();
        $this->signIn('admin@river.test', 'SLM');
        $this->getJson('/api/v1/appointments')->assertOk()->assertJsonCount(0, 'data');
        $this->putJson("/api/v1/appointments/{$appointmentId}/status", ['status' => 'CHECKED_IN'])->assertNotFound();
        $this->assertNotEquals($river->id, Appointment::findOrFail($appointmentId)->hospital_id);

        $this->signIn('admin@lotus.test', 'CHN');
        $this->getJson('/api/v1/appointments')->assertOk()->assertJsonCount(0, 'data');
        $this->putJson("/api/v1/appointments/{$appointmentId}/cancel", ['cancellation_reason' => 'Wrong branch'])->assertNotFound();
    }

    public function test_duplicate_request_key_with_changed_booking_is_rejected_and_validation_is_useful(): void
    {
        $this->signIn('reception@lotus.test');
        $profile = $this->profile();
        $payload = ['patient_id' => Patient::where('hospital_id', $profile->hospital_id)->value('id'), 'doctor_profile_id' => $profile->id, 'appointment_date' => '2026-10-05', 'starts_at' => '09:00', 'type' => 'NEW', 'request_key' => '550e8400-e29b-41d4-a716-446655440099'];
        $this->postJson('/api/v1/appointments', $payload)->assertCreated();
        $this->postJson('/api/v1/appointments', [...$payload, 'starts_at' => '09:15'])->assertUnprocessable()->assertJsonValidationErrors('request_key');
        $this->postJson('/api/v1/appointments', [...$payload, 'request_key' => 'bad'])->assertUnprocessable()->assertJsonValidationErrors('request_key');
        $this->postJson('/api/v1/appointments', [...$payload, 'request_key' => null, 'type' => 'INVALID'])->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_doctor_cannot_view_or_change_another_doctors_queue(): void
    {
        $own = $this->profile();
        $other = DoctorProfile::factory()->create(['hospital_id' => $own->hospital_id, 'branch_id' => $own->branch_id, 'department_id' => $own->department_id]);
        $other->schedules()->create(['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '12:00', 'slot_duration_minutes' => 15, 'capacity_per_slot' => 1, 'status' => 'active']);
        $this->signIn('reception@lotus.test');
        $appointmentId = $this->postJson('/api/v1/appointments', [
            'patient_id' => Patient::where('hospital_id', $own->hospital_id)->value('id'),
            'doctor_profile_id' => $other->id, 'appointment_date' => '2026-10-05', 'starts_at' => '09:00', 'type' => 'NEW',
        ])->assertCreated()->json('data.id');

        $this->signIn('doctor@lotus.test');
        $this->getJson('/api/v1/appointments?date=2026-10-05')->assertOk()->assertJsonCount(0, 'data');
        $this->putJson("/api/v1/appointments/{$appointmentId}/status", ['status' => 'CONSULTING'])->assertForbidden();
    }

    private function profile(): DoctorProfile
    {
        $branch = Branch::where('code', 'CBE')->firstOrFail();
        $doctor = User::where('email', 'doctor@lotus.test')->firstOrFail();
        $profile = DoctorProfile::factory()->create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'user_id' => $doctor->id]);
        $profile->schedules()->create(['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '12:00', 'slot_duration_minutes' => 15, 'capacity_per_slot' => 1, 'status' => 'active']);

        return $profile;
    }

    private function signIn(string $email, string $branchCode = 'CBE'): void
    {
        $user = User::where('email', $email)->firstOrFail();
        $membership = $user->memberships()->whereHas('branch', fn ($query) => $query->where('code', $branchCode))->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);
    }
}
