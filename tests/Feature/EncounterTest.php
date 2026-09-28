<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\DoctorProfile;
use App\Models\Encounter;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class EncounterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_assigned_doctor_opens_one_encounter_and_repeated_start_returns_it(): void
    {
        $profile = $this->profile();
        $appointment = $this->waitingAppointment($profile);
        $this->signIn('doctor@lotus.test');

        $first = $this->putJson("/api/v1/appointments/{$appointment->id}/status", ['status' => 'CONSULTING'])
            ->assertOk()
            ->assertJsonPath('data.status', 'CONSULTING')
            ->assertJsonPath('encounter.encounter_type', 'OPD')
            ->assertJsonPath('encounter.status', 'ACTIVE')
            ->assertJsonPath('replayed', false);
        $encounterId = $first->json('encounter.id');
        $this->putJson("/api/v1/appointments/{$appointment->id}/status", ['status' => 'CONSULTING'])
            ->assertOk()
            ->assertJsonPath('encounter.id', $encounterId)
            ->assertJsonPath('replayed', true);
        $this->postJson("/api/v1/appointments/{$appointment->id}/encounter")
            ->assertOk()
            ->assertJsonPath('data.id', $encounterId)
            ->assertJsonPath('replayed', true);
        $this->getJson("/api/v1/encounters/{$encounterId}")
            ->assertOk()
            ->assertJsonPath('data.appointment_id', $appointment->id)
            ->assertJsonPath('data.patient.uhid', $appointment->patient->uhid)
            ->assertJsonPath('data.doctor_profile.user.email', null);

        $this->assertDatabaseCount('encounters', 1);
        $this->assertDatabaseHas('encounters', [
            'id' => $encounterId,
            'hospital_id' => $appointment->hospital_id,
            'branch_id' => $appointment->branch_id,
            'patient_id' => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'doctor_profile_id' => $profile->id,
            'department_id' => $profile->department_id,
            'opened_by' => $profile->user_id,
        ]);
        $this->assertSame(1, AuditLog::where('module', 'encounters')->where('action', 'opened')->count());
    }

    public function test_non_waiting_appointment_fails_without_partial_encounter(): void
    {
        $profile = $this->profile();
        $appointment = $this->waitingAppointment($profile, 'CHECKED_IN');
        $this->signIn('doctor@lotus.test');

        $this->postJson("/api/v1/appointments/{$appointment->id}/encounter")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('appointment');
        $this->assertDatabaseCount('encounters', 0);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'CHECKED_IN']);
    }

    public function test_clinical_access_is_limited_to_assigned_doctor(): void
    {
        $profile = $this->profile();
        $appointment = $this->waitingAppointment($profile);
        $this->signIn('admin@lotus.test');
        $this->postJson("/api/v1/appointments/{$appointment->id}/encounter")->assertForbidden();
        $this->signIn('reception@lotus.test');
        $this->postJson("/api/v1/appointments/{$appointment->id}/encounter")->assertForbidden();

        $other = User::factory()->create(['hospital_id' => $profile->hospital_id, 'email' => 'other.doctor@lotus.test']);
        $role = Role::where('name', 'DOCTOR')->firstOrFail();
        $other->memberships()->create(['hospital_id' => $profile->hospital_id, 'branch_id' => $profile->branch_id, 'role_id' => $role->id, 'status' => 'active']);
        DoctorProfile::factory()->create(['hospital_id' => $profile->hospital_id, 'branch_id' => $profile->branch_id, 'user_id' => $other->id, 'department_id' => $profile->department_id]);
        $this->signIn('other.doctor@lotus.test');
        $this->postJson("/api/v1/appointments/{$appointment->id}/encounter")->assertForbidden();
        $this->assertDatabaseCount('encounters', 0);
    }

    public function test_encounter_provenance_is_immutable_and_records_cannot_be_deleted(): void
    {
        $profile = $this->profile();
        $appointment = $this->waitingAppointment($profile, 'CONSULTING');
        $encounter = Encounter::factory()->create(['appointment_id' => $appointment->id]);

        try {
            $encounter->patient_id = Patient::factory()->forBranch($profile->branch)->create()->id;
            $encounter->save();
            $this->fail('Changing encounter ownership should fail.');
        } catch (LogicException) {
            $this->assertDatabaseHas('encounters', ['id' => $encounter->id, 'patient_id' => $appointment->patient_id]);
        }

        $this->expectException(LogicException::class);
        $encounter->delete();
    }

    private function waitingAppointment(DoctorProfile $profile, string $status = 'WAITING'): Appointment
    {
        return Appointment::factory()->create([
            'hospital_id' => $profile->hospital_id,
            'branch_id' => $profile->branch_id,
            'patient_id' => Patient::factory()->forBranch($profile->branch)->create()->id,
            'doctor_profile_id' => $profile->id,
            'status' => $status,
            'created_by' => User::where('email', 'reception@lotus.test')->value('id'),
        ]);
    }

    private function profile(): DoctorProfile
    {
        $hospital = Hospital::where('code', 'LOTUS')->firstOrFail();
        $branch = Branch::where('hospital_id', $hospital->id)->where('code', 'CBE')->firstOrFail();
        $doctor = User::where('email', 'doctor@lotus.test')->firstOrFail();

        return DoctorProfile::firstOrCreate(
            ['hospital_id' => $hospital->id, 'branch_id' => $branch->id, 'user_id' => $doctor->id],
            ['department_id' => Department::where('branch_id', $branch->id)->value('id'), 'registration_number' => 'ENC-DOCTOR', 'qualification' => 'MBBS', 'specialization' => 'General Medicine', 'consultation_fee' => 500, 'status' => 'active'],
        )->fresh('branch');
    }

    private function signIn(string $email): void
    {
        $user = User::where('email', $email)->firstOrFail();
        $membership = $user->memberships()->whereHas('branch', fn ($query) => $query->where('code', 'CBE'))->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);
    }
}
