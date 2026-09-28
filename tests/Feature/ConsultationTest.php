<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Department;
use App\Models\DoctorProfile;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_assigned_doctor_saves_finalizes_and_amends_without_overwriting_original(): void
    {
        [$appointment, $encounterId] = $this->openEncounter();
        $draft = ['chief_complaint' => 'Fever', 'history' => 'Two days', 'examination' => 'Stable', 'clinical_notes' => 'Hydration advised', 'follow_up_date' => now()->addDays(7)->toDateString()];
        $this->putJson("/api/v1/encounters/{$encounterId}/consultation", $draft)->assertOk()->assertJsonPath('data.status', 'DRAFT');
        $this->putJson("/api/v1/encounters/{$encounterId}/consultation/finalize", $draft)->assertOk()->assertJsonPath('data.status', 'FINALIZED');
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'COMPLETED']);
        $this->assertDatabaseHas('encounters', ['id' => $encounterId, 'status' => 'CLOSED']);

        $amended = [...$draft, 'clinical_notes' => 'Hydration and rest advised', 'reason' => 'Clarified advice'];
        $this->postJson("/api/v1/encounters/{$encounterId}/consultation/amendments", $amended)->assertCreated()->assertJsonPath('data.reason', 'Clarified advice');
        $this->assertDatabaseHas('consultations', ['encounter_id' => $encounterId, 'clinical_notes' => 'Hydration advised', 'status' => 'FINALIZED']);
        $this->assertDatabaseHas('consultation_amendments', ['clinical_notes' => 'Hydration and rest advised', 'reason' => 'Clarified advice']);
        $this->getJson("/api/v1/encounters/{$encounterId}/consultation")->assertOk()->assertJsonPath('effective_consultation.clinical_notes', 'Hydration and rest advised');
        $this->get("/encounters/{$encounterId}/consultation")->assertOk()->assertSee('Finalized consultation')->assertSee('Clarified advice');
    }

    public function test_finalization_requires_clinical_content_and_failed_attempt_keeps_encounter_active(): void
    {
        [$appointment, $encounterId] = $this->openEncounter();
        $this->putJson("/api/v1/encounters/{$encounterId}/consultation/finalize", ['history' => 'History only'])
            ->assertUnprocessable()->assertJsonValidationErrors('consultation');
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'CONSULTING']);
        $this->assertDatabaseHas('encounters', ['id' => $encounterId, 'status' => 'ACTIVE']);
        $this->assertDatabaseCount('consultations', 0);
    }

    public function test_non_clinical_roles_cannot_view_or_change_consultation(): void
    {
        [, $encounterId] = $this->openEncounter();
        $this->signIn('admin@lotus.test');
        $this->getJson("/api/v1/encounters/{$encounterId}/consultation")->assertForbidden();
        $this->putJson("/api/v1/encounters/{$encounterId}/consultation", ['chief_complaint' => 'Forged'])->assertForbidden();
        $this->signIn('reception@lotus.test');
        $this->get("/encounters/{$encounterId}/consultation")->assertForbidden();
    }

    /** @return array{Appointment, int} */
    private function openEncounter(): array
    {
        $profile = $this->profile();
        $appointment = Appointment::factory()->create(['hospital_id' => $profile->hospital_id, 'branch_id' => $profile->branch_id, 'patient_id' => Patient::factory()->forBranch($profile->branch)->create()->id, 'doctor_profile_id' => $profile->id, 'status' => 'WAITING', 'created_by' => User::where('email', 'reception@lotus.test')->value('id')]);
        $this->signIn('doctor@lotus.test');
        $encounterId = $this->putJson("/api/v1/appointments/{$appointment->id}/status", ['status' => 'CONSULTING'])->assertOk()->json('encounter.id');

        return [$appointment, $encounterId];
    }

    private function profile(): DoctorProfile
    {
        $hospital = Hospital::where('code', 'LOTUS')->firstOrFail();
        $branch = Branch::where('hospital_id', $hospital->id)->where('code', 'CBE')->firstOrFail();
        $doctor = User::where('email', 'doctor@lotus.test')->firstOrFail();

        return DoctorProfile::firstOrCreate(['hospital_id' => $hospital->id, 'branch_id' => $branch->id, 'user_id' => $doctor->id], ['department_id' => Department::where('branch_id', $branch->id)->value('id'), 'registration_number' => 'CONSULT-DOCTOR', 'qualification' => 'MBBS', 'specialization' => 'General Medicine', 'consultation_fee' => 500, 'status' => 'active'])->fresh('branch');
    }

    private function signIn(string $email): void
    {
        $user = User::where('email', $email)->firstOrFail();
        $membership = $user->memberships()->whereHas('branch', fn ($query) => $query->where('code', 'CBE'))->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);
    }
}
