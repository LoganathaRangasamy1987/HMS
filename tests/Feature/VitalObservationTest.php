<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Department;
use App\Models\DoctorProfile;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Models\VitalObservation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class VitalObservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_assigned_doctor_records_attributed_unit_explicit_vitals(): void
    {
        $encounterId = $this->openEncounter();
        $measuredAt = now('Asia/Kolkata')->subMinute()->format('Y-m-d\TH:i');
        $id = $this->postJson("/api/v1/encounters/{$encounterId}/vitals", ['temperature' => '37.20', 'pulse' => 82, 'respiratory_rate' => 18, 'systolic_bp' => 120, 'diastolic_bp' => 80, 'oxygen_saturation' => '98.00', 'weight' => '70.50', 'height' => '172.00', 'measured_at' => $measuredAt])->assertCreated()->assertJsonPath('data.temperature_unit', '°C')->assertJsonPath('data.blood_pressure_unit', 'mmHg')->json('data.id');
        $this->assertDatabaseHas('vital_observations', ['id' => $id, 'encounter_id' => $encounterId, 'pulse' => 82, 'recorded_by' => User::where('email', 'doctor@lotus.test')->value('id')]);
        $this->get("/encounters/{$encounterId}/consultation")->assertOk()->assertSee('37.20 °C')->assertSee('120/80 mmHg');
    }

    public function test_invalid_empty_and_future_vitals_do_not_create_records(): void
    {
        $encounterId = $this->openEncounter();
        $this->postJson("/api/v1/encounters/{$encounterId}/vitals", ['temperature' => '99.00', 'measured_at' => now('Asia/Kolkata')->format('Y-m-d\TH:i')])->assertUnprocessable()->assertJsonValidationErrors('temperature');
        $this->postJson("/api/v1/encounters/{$encounterId}/vitals", ['notes' => 'No measurement', 'measured_at' => now('Asia/Kolkata')->format('Y-m-d\TH:i')])->assertUnprocessable()->assertJsonValidationErrors('vitals');
        $this->postJson("/api/v1/encounters/{$encounterId}/vitals", ['pulse' => 80, 'measured_at' => now('Asia/Kolkata')->addHour()->format('Y-m-d\TH:i')])->assertUnprocessable()->assertJsonValidationErrors('measured_at');
        $this->assertDatabaseCount('vital_observations', 0);
    }

    public function test_vitals_are_doctor_scoped_immutable_and_blocked_after_encounter_closes(): void
    {
        $encounterId = $this->openEncounter();
        $payload = ['pulse' => 75, 'measured_at' => now('Asia/Kolkata')->subMinute()->format('Y-m-d\TH:i')];
        $vitalId = $this->postJson("/api/v1/encounters/{$encounterId}/vitals", $payload)->assertCreated()->json('data.id');
        $this->signIn('admin@lotus.test');
        $this->postJson("/api/v1/encounters/{$encounterId}/vitals", $payload)->assertForbidden();
        $this->signIn('doctor@lotus.test');
        $this->putJson("/api/v1/encounters/{$encounterId}/consultation/finalize", ['chief_complaint' => 'Review', 'clinical_notes' => 'Completed'])->assertOk();
        $this->postJson("/api/v1/encounters/{$encounterId}/vitals", $payload)->assertNotFound();
        $this->expectException(LogicException::class);
        VitalObservation::findOrFail($vitalId)->update(['pulse' => 76]);
    }

    private function openEncounter(): int
    {
        $hospital = Hospital::where('code', 'LOTUS')->firstOrFail();
        $branch = Branch::where('hospital_id', $hospital->id)->where('code', 'CBE')->firstOrFail();
        $doctor = User::where('email', 'doctor@lotus.test')->firstOrFail();
        $profile = DoctorProfile::firstOrCreate(['hospital_id' => $hospital->id, 'branch_id' => $branch->id, 'user_id' => $doctor->id], ['department_id' => Department::where('branch_id', $branch->id)->value('id'), 'registration_number' => 'VITAL-DOCTOR', 'qualification' => 'MBBS', 'specialization' => 'General Medicine', 'consultation_fee' => 500, 'status' => 'active'])->fresh('branch');
        $appointment = Appointment::factory()->create(['hospital_id' => $hospital->id, 'branch_id' => $branch->id, 'patient_id' => Patient::factory()->forBranch($branch)->create()->id, 'doctor_profile_id' => $profile->id, 'status' => 'WAITING', 'created_by' => User::where('email', 'reception@lotus.test')->value('id')]);
        $this->signIn('doctor@lotus.test');

        return $this->putJson("/api/v1/appointments/{$appointment->id}/status", ['status' => 'CONSULTING'])->assertOk()->json('encounter.id');
    }

    private function signIn(string $email): void
    {
        $user = User::where('email', $email)->firstOrFail();
        $membership = $user->memberships()->whereHas('branch', fn ($query) => $query->where('code', 'CBE'))->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);
    }
}
