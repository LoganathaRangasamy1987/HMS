<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Department;
use App\Models\DoctorProfile;
use App\Models\Hospital;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\Prescription;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class PrescriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_assigned_doctor_saves_and_prints_snapshot_prescription_with_allergies(): void
    {
        [$encounterId, $patient] = $this->openEncounter();
        PatientAllergy::create(['hospital_id' => $patient->hospital_id, 'patient_id' => $patient->id, 'branch_id' => $patient->branch_id, 'recorded_by' => User::where('email', 'doctor@lotus.test')->value('id'), 'allergen' => 'Penicillin', 'severity' => 'severe', 'status' => 'active']);
        $medicine = Medicine::where('hospital_id', $patient->hospital_id)->where('code', 'PARA500')->firstOrFail();
        $id = $this->postJson("/api/v1/encounters/{$encounterId}/prescription", $this->payload($medicine->id))->assertCreated()
            ->assertJsonPath('data.items.0.medicine_name', 'Paracetamol')->json('data.id');
        $medicine->update(['name' => 'Renamed catalog medicine', 'strength' => '650 mg']);

        $this->assertDatabaseHas('prescription_items', ['prescription_id' => $id, 'medicine_name' => 'Paracetamol', 'strength' => '500 mg', 'dose' => '1 tablet']);
        $this->get("/encounters/{$encounterId}/consultation")->assertOk()->assertSee('Paracetamol')->assertSee('Print prescription');
        $this->get("/encounters/{$encounterId}/prescription/print")->assertOk()->assertSee('Paracetamol')->assertSee('Penicillin')->assertSee('Dr Arjun Kumar');
    }

    public function test_invalid_or_duplicate_prescriptions_are_rejected(): void
    {
        [$encounterId, $patient] = $this->openEncounter();
        $medicine = Medicine::where('hospital_id', $patient->hospital_id)->where('code', 'PARA500')->firstOrFail();
        $this->postJson("/api/v1/encounters/{$encounterId}/prescription", ['items' => [['medicine_id' => $medicine->id, 'dose' => '', 'frequency' => 'Daily', 'duration' => '3 days', 'route' => 'Oral']]])->assertUnprocessable()->assertJsonValidationErrors('items.0.dose');
        $this->postJson("/api/v1/encounters/{$encounterId}/prescription", $this->payload($medicine->id))->assertCreated();
        $this->postJson("/api/v1/encounters/{$encounterId}/prescription", $this->payload($medicine->id))->assertUnprocessable()->assertJsonValidationErrors('prescription');
        $this->assertDatabaseCount('prescriptions', 1);
    }

    public function test_prescriptions_are_doctor_scoped_and_blocked_after_encounter_closes(): void
    {
        [$encounterId, $patient] = $this->openEncounter();
        $medicine = Medicine::where('hospital_id', $patient->hospital_id)->where('code', 'PARA500')->firstOrFail();
        $this->signIn('admin@lotus.test');
        $this->postJson("/api/v1/encounters/{$encounterId}/prescription", $this->payload($medicine->id))->assertForbidden();
        $this->signIn('doctor@lotus.test');
        $this->putJson("/api/v1/encounters/{$encounterId}/consultation/finalize", ['chief_complaint' => 'Review', 'clinical_notes' => 'Completed'])->assertOk();
        $this->postJson("/api/v1/encounters/{$encounterId}/prescription", $this->payload($medicine->id))->assertNotFound();
    }

    public function test_saved_prescriptions_are_immutable(): void
    {
        [$encounterId, $patient] = $this->openEncounter();
        $medicine = Medicine::where('hospital_id', $patient->hospital_id)->where('code', 'PARA500')->firstOrFail();
        $id = $this->postJson("/api/v1/encounters/{$encounterId}/prescription", $this->payload($medicine->id))->assertCreated()->json('data.id');
        $this->expectException(LogicException::class);
        Prescription::findOrFail($id)->update(['notes' => 'Changed']);
    }

    /** @return array<string, mixed> */
    private function payload(int $medicineId): array
    {
        return ['notes' => 'Complete the course', 'items' => [['medicine_id' => $medicineId, 'dose' => '1 tablet', 'frequency' => 'Twice daily', 'duration' => '3 days', 'route' => 'Oral', 'timing' => 'After food', 'advice' => 'Take with water']]];
    }

    /** @return array{int, Patient} */
    private function openEncounter(): array
    {
        $hospital = Hospital::where('code', 'LOTUS')->firstOrFail();
        $branch = Branch::where('hospital_id', $hospital->id)->where('code', 'CBE')->firstOrFail();
        $doctor = User::where('email', 'doctor@lotus.test')->firstOrFail();
        $profile = DoctorProfile::firstOrCreate(['hospital_id' => $hospital->id, 'branch_id' => $branch->id, 'user_id' => $doctor->id], ['department_id' => Department::where('branch_id', $branch->id)->value('id'), 'registration_number' => 'RX-DOCTOR', 'qualification' => 'MBBS', 'specialization' => 'General Medicine', 'consultation_fee' => 500, 'status' => 'active'])->fresh('branch');
        $patient = Patient::factory()->forBranch($branch)->create();
        $appointment = Appointment::factory()->create(['hospital_id' => $hospital->id, 'branch_id' => $branch->id, 'patient_id' => $patient->id, 'doctor_profile_id' => $profile->id, 'status' => 'WAITING', 'created_by' => User::where('email', 'reception@lotus.test')->value('id')]);
        $this->signIn('doctor@lotus.test');

        return [$this->putJson("/api/v1/appointments/{$appointment->id}/status", ['status' => 'CONSULTING'])->assertOk()->json('encounter.id'), $patient];
    }

    private function signIn(string $email): void
    {
        $user = User::where('email', $email)->firstOrFail();
        $membership = $user->memberships()->whereHas('branch', fn ($query) => $query->where('code', 'CBE'))->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);

    }
}
