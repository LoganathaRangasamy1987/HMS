<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Diagnosis;
use App\Models\DoctorProfile;
use App\Models\Hospital;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Patient360Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_receptionist_sees_only_operational_and_financial_timeline_sections(): void
    {
        [$patient, $encounterId] = $this->patientWithEncounter();
        Diagnosis::factory()->create(['encounter_id' => $encounterId, 'description' => 'Private clinical diagnosis']);
        PatientAllergy::factory()->create(['patient_id' => $patient->id, 'allergen' => 'Private allergen']);
        $invoice = $this->invoice($patient, 'INV-360-001');
        Payment::factory()->create(['invoice_id' => $invoice->id, 'amount' => '20.00']);
        $this->signIn('reception@lotus.test');

        $response = $this->getJson("/api/v1/patients/{$patient->id}/timeline")->assertOk()
            ->assertJsonPath('permissions.canClinical', false)->assertJsonPath('permissions.canBilling', true);
        $types = collect($response->json('timeline.data'))->pluck('event_type');
        $this->assertTrue($types->contains('appointment'));
        $this->assertTrue($types->contains('invoice'));
        $this->assertTrue($types->contains('payment'));
        $this->assertFalse($types->contains('diagnosis'));
        $this->assertFalse($types->contains('allergy'));
        $this->getJson("/api/v1/patients/{$patient->id}/timeline?section=clinical")->assertForbidden();
    }

    public function test_doctor_sees_only_assigned_clinical_events_and_no_financial_section(): void
    {
        [$patient, $encounterId] = $this->patientWithEncounter();
        Diagnosis::factory()->create(['encounter_id' => $encounterId, 'description' => 'Assigned diagnosis']);
        PatientAllergy::factory()->create(['patient_id' => $patient->id, 'allergen' => 'Pollen']);
        $this->invoice($patient, 'INV-360-002');
        $this->signIn('doctor@lotus.test');

        $response = $this->getJson("/api/v1/patients/{$patient->id}/timeline?section=clinical")->assertOk()
            ->assertJsonPath('permissions.canClinical', true)->assertJsonPath('permissions.canBilling', false);
        $types = collect($response->json('timeline.data'))->pluck('event_type');
        $this->assertTrue($types->contains('encounter'));
        $this->assertTrue($types->contains('diagnosis'));
        $this->assertTrue($types->contains('allergy'));
        $this->assertFalse($types->contains('invoice'));
        $this->get("/patients/{$patient->id}/timeline")->assertOk()->assertSee('Patient 360')->assertSee('Assigned diagnosis')->assertDontSee('INV-360-002');
        $this->getJson("/api/v1/patients/{$patient->id}/timeline?section=billing")->assertForbidden();
    }

    public function test_timeline_is_tenant_scoped_branch_scoped_and_page_size_bounded(): void
    {
        [$patient] = $this->patientWithEncounter();
        foreach (range(1, 12) as $number) {
            $this->invoice($patient, sprintf('INV-360-%03d', $number + 10));
        }
        $chennai = Branch::where('hospital_id', $patient->hospital_id)->where('code', 'CHN')->firstOrFail();
        Invoice::factory()->create(['hospital_id' => $patient->hospital_id, 'branch_id' => $chennai->id, 'patient_id' => $patient->id, 'number' => 'FOREIGN-BRANCH-INVOICE', 'status' => 'ISSUED', 'total' => '10.00', 'issued_at' => now()]);
        $this->signIn('admin@lotus.test');

        $response = $this->getJson("/api/v1/patients/{$patient->id}/timeline?section=billing&per_page=5")->assertOk()
            ->assertJsonCount(5, 'timeline.data')->assertJsonPath('timeline.per_page', 5);
        $this->assertNotContains('FOREIGN-BRANCH-INVOICE', collect($response->json('timeline.data'))->pluck('detail')->all());
        $this->getJson("/api/v1/patients/{$patient->id}/timeline?per_page=100")->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $foreign = Patient::factory()->forBranch(Branch::whereHas('hospital', fn ($query) => $query->where('code', 'RIVER'))->firstOrFail())->create();
        $this->getJson("/api/v1/patients/{$foreign->id}/timeline")->assertNotFound();
    }

    /** @return array{Patient, int} */
    private function patientWithEncounter(): array
    {
        $hospital = Hospital::where('code', 'LOTUS')->firstOrFail();
        $branch = Branch::where('hospital_id', $hospital->id)->where('code', 'CBE')->firstOrFail();
        $doctor = User::where('email', 'doctor@lotus.test')->firstOrFail();
        $profile = DoctorProfile::firstOrCreate(['hospital_id' => $hospital->id, 'branch_id' => $branch->id, 'user_id' => $doctor->id], ['department_id' => Department::where('branch_id', $branch->id)->value('id'), 'registration_number' => 'P360-DOCTOR', 'qualification' => 'MBBS', 'specialization' => 'General Medicine', 'consultation_fee' => 500, 'status' => 'active'])->fresh('branch');
        $patient = Patient::factory()->forBranch($branch)->create();
        $appointment = Appointment::factory()->create(['hospital_id' => $hospital->id, 'branch_id' => $branch->id, 'patient_id' => $patient->id, 'doctor_profile_id' => $profile->id, 'status' => 'WAITING', 'created_by' => User::where('email', 'reception@lotus.test')->value('id')]);
        $this->signIn('doctor@lotus.test');
        $encounterId = $this->putJson("/api/v1/appointments/{$appointment->id}/status", ['status' => 'CONSULTING'])->assertOk()->json('encounter.id');

        return [$patient, $encounterId];
    }

    private function invoice(Patient $patient, string $number): Invoice
    {
        return Invoice::factory()->create(['hospital_id' => $patient->hospital_id, 'branch_id' => $patient->branch_id, 'patient_id' => $patient->id, 'number' => $number, 'status' => 'ISSUED', 'total' => '100.00', 'issued_at' => now()]);
    }

    private function signIn(string $email): void
    {
        $user = User::where('email', $email)->firstOrFail();
        $membership = $user->memberships()->whereHas('branch', fn ($query) => $query->where('code', 'CBE'))->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);

    }
}
