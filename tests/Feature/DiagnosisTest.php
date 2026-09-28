<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Diagnosis;
use App\Models\DiagnosisCorrection;
use App\Models\DoctorProfile;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class DiagnosisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_assigned_doctor_records_an_attributed_coded_diagnosis(): void
    {
        $encounterId = $this->openEncounter();
        $diagnosisId = $this->postJson("/api/v1/encounters/{$encounterId}/diagnoses", [
            'type' => 'PROVISIONAL', 'description' => 'Acute upper respiratory infection',
            'code_system' => 'ICD-10', 'code' => 'J06.9',
            'diagnosed_at' => now('Asia/Kolkata')->subMinute()->format('Y-m-d\\TH:i'),
        ])->assertCreated()->assertJsonPath('data.type', 'PROVISIONAL')->json('data.id');

        $this->assertDatabaseHas('diagnoses', [
            'id' => $diagnosisId, 'encounter_id' => $encounterId,
            'description' => 'Acute upper respiratory infection',
            'authored_by' => User::where('email', 'doctor@lotus.test')->value('id'),
        ]);
        $this->get("/encounters/{$encounterId}/consultation")->assertOk()
            ->assertSee('Acute upper respiratory infection')->assertSee('ICD-10 J06.9');
    }

    public function test_correction_preserves_the_original_and_becomes_effective(): void
    {
        $encounterId = $this->openEncounter();
        $diagnosisId = $this->recordDiagnosis($encounterId);
        $correctionId = $this->postJson("/api/v1/encounters/{$encounterId}/diagnoses/{$diagnosisId}/corrections", [
            'type' => 'FINAL', 'description' => 'Viral upper respiratory infection',
            'code_system' => 'ICD-10', 'code' => 'J06.9', 'reason' => 'Confirmed after examination',
        ])->assertCreated()->assertJsonPath('data.type', 'FINAL')->json('data.id');

        $this->assertDatabaseHas('diagnoses', ['id' => $diagnosisId, 'type' => 'PROVISIONAL', 'description' => 'Suspected respiratory infection']);
        $this->assertDatabaseHas('diagnosis_corrections', ['id' => $correctionId, 'diagnosis_id' => $diagnosisId, 'type' => 'FINAL', 'reason' => 'Confirmed after examination']);
        $this->get("/encounters/{$encounterId}/consultation")->assertOk()
            ->assertSee('Viral upper respiratory infection')->assertSee('Confirmed after examination');
    }

    public function test_invalid_diagnoses_are_rejected_without_writes(): void
    {
        $encounterId = $this->openEncounter();
        $base = ['type' => 'PROVISIONAL', 'description' => 'Suspected infection', 'diagnosed_at' => now('Asia/Kolkata')->subMinute()->format('Y-m-d\\TH:i')];
        $this->postJson("/api/v1/encounters/{$encounterId}/diagnoses", [...$base, 'code_system' => 'ICD-10'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson("/api/v1/encounters/{$encounterId}/diagnoses", [...$base, 'type' => 'CONFIRMED'])
            ->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->postJson("/api/v1/encounters/{$encounterId}/diagnoses", [...$base, 'diagnosed_at' => now('Asia/Kolkata')->addHour()->format('Y-m-d\\TH:i')])
            ->assertUnprocessable()->assertJsonValidationErrors('diagnosed_at');
        $this->assertDatabaseCount('diagnoses', 0);
    }

    public function test_access_is_doctor_scoped_and_new_entries_stop_after_close(): void
    {
        $encounterId = $this->openEncounter();
        $diagnosisId = $this->recordDiagnosis($encounterId);
        $this->signIn('admin@lotus.test');
        $this->postJson("/api/v1/encounters/{$encounterId}/diagnoses", $this->diagnosisPayload())->assertForbidden();
        $this->signIn('doctor@lotus.test');
        $this->putJson("/api/v1/encounters/{$encounterId}/consultation/finalize", ['chief_complaint' => 'Review', 'clinical_notes' => 'Completed'])->assertOk();
        $this->postJson("/api/v1/encounters/{$encounterId}/diagnoses", $this->diagnosisPayload())->assertNotFound();
        $this->postJson("/api/v1/encounters/{$encounterId}/diagnoses/{$diagnosisId}/corrections", [
            'type' => 'FINAL', 'description' => 'Final diagnosis after review', 'reason' => 'Post-consultation clarification',
        ])->assertCreated();
    }

    public function test_diagnoses_and_corrections_are_immutable(): void
    {
        $encounterId = $this->openEncounter();
        $diagnosis = Diagnosis::findOrFail($this->recordDiagnosis($encounterId));
        $correction = DiagnosisCorrection::factory()->create(['diagnosis_id' => $diagnosis->id]);

        try {
            $diagnosis->update(['description' => 'Changed']);
            $this->fail('The diagnosis update should have been blocked.');
        } catch (LogicException) {
            $this->assertDatabaseHas('diagnoses', ['id' => $diagnosis->id, 'description' => 'Suspected respiratory infection']);
        }

        $this->expectException(LogicException::class);
        $correction->delete();
    }

    private function recordDiagnosis(int $encounterId): int
    {
        return $this->postJson("/api/v1/encounters/{$encounterId}/diagnoses", $this->diagnosisPayload())->assertCreated()->json('data.id');
    }

    /** @return array<string, string> */
    private function diagnosisPayload(): array
    {
        return ['type' => 'PROVISIONAL', 'description' => 'Suspected respiratory infection', 'diagnosed_at' => now('Asia/Kolkata')->subMinute()->format('Y-m-d\\TH:i')];
    }

    private function openEncounter(): int
    {
        $hospital = Hospital::where('code', 'LOTUS')->firstOrFail();
        $branch = Branch::where('hospital_id', $hospital->id)->where('code', 'CBE')->firstOrFail();
        $doctor = User::where('email', 'doctor@lotus.test')->firstOrFail();
        $profile = DoctorProfile::firstOrCreate(
            ['hospital_id' => $hospital->id, 'branch_id' => $branch->id, 'user_id' => $doctor->id],
            ['department_id' => Department::where('branch_id', $branch->id)->value('id'), 'registration_number' => 'DIAG-DOCTOR', 'qualification' => 'MBBS', 'specialization' => 'General Medicine', 'consultation_fee' => 500, 'status' => 'active']
        )->fresh('branch');
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
