<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\PatientDocument;
use App\Models\PatientMedicalHistory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PatientClinicalHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_doctor_can_record_and_update_attributed_allergy_and_history(): void
    {
        $doctor = $this->signIn('doctor@lotus.test');
        $patient = $this->patient();
        $this->postJson("/api/v1/patients/{$patient->id}/allergies", ['allergen' => 'Penicillin', 'reaction' => 'Rash', 'severity' => 'moderate', 'status' => 'active'])
            ->assertCreated()->assertJsonPath('data.recorded_by', $doctor->id);
        $allergy = PatientAllergy::firstOrFail();
        $this->putJson("/api/v1/patients/{$patient->id}/allergies/{$allergy->id}", ['allergen' => 'Penicillin', 'reaction' => 'Rash', 'severity' => 'severe', 'status' => 'active'])
            ->assertOk()->assertJsonPath('data.severity', 'severe');
        $this->postJson("/api/v1/patients/{$patient->id}/medical-history", ['condition' => 'Asthma', 'onset_date_unknown' => true, 'status' => 'active', 'notes' => 'Patient reported'])
            ->assertCreated()->assertJsonPath('data.onset_date', null);

        $this->assertDatabaseHas('patient_allergies', ['hospital_id' => $patient->hospital_id, 'patient_id' => $patient->id, 'branch_id' => $this->branch()->id, 'recorded_by' => $doctor->id, 'severity' => 'severe']);
        $this->assertDatabaseHas('patient_medical_histories', ['patient_id' => $patient->id, 'condition' => 'Asthma', 'onset_date_unknown' => true]);
        $this->assertSame(3, AuditLog::whereIn('module', ['patient_allergies', 'patient_medical_history'])->count());
    }

    public function test_clinical_history_is_doctor_only_and_not_exposed_to_demographic_roles(): void
    {
        $patient = $this->patient();
        foreach (['reception@lotus.test', 'admin@lotus.test'] as $email) {
            $this->signIn($email);
            $this->getJson("/api/v1/patients/{$patient->id}/clinical-history")->assertForbidden();
            $this->postJson("/api/v1/patients/{$patient->id}/allergies", ['allergen' => 'Private'])->assertForbidden();
            $this->post("/patients/{$patient->id}/documents", [])->assertForbidden();
            $this->get("/patients/{$patient->id}")->assertOk()->assertDontSee('Clinical history');
        }
        $this->assertSame(0, PatientAllergy::count());
    }

    public function test_another_hospitals_patient_and_nested_records_return_not_found(): void
    {
        $this->signIn('doctor@lotus.test');
        $foreignPatient = Patient::factory()->forBranch($this->branch('RIVER', 'SLM'))->create();
        $localPatient = $this->patient();
        $foreignAllergy = PatientAllergy::create(['hospital_id' => $foreignPatient->hospital_id, 'patient_id' => $foreignPatient->id, 'branch_id' => $foreignPatient->branch_id, 'recorded_by' => User::where('email', 'admin@river.test')->value('id'), 'allergen' => 'Private', 'severity' => 'unknown', 'status' => 'active']);

        $this->getJson("/api/v1/patients/{$foreignPatient->id}/clinical-history")->assertNotFound();
        $this->putJson("/api/v1/patients/{$localPatient->id}/allergies/{$foreignAllergy->id}", ['allergen' => 'Forged', 'severity' => 'mild', 'status' => 'active'])->assertNotFound();
    }

    public function test_history_validates_dates_and_required_content_without_writing(): void
    {
        $this->signIn('doctor@lotus.test');
        $patient = $this->patient();
        $this->postJson("/api/v1/patients/{$patient->id}/medical-history", ['condition' => '', 'onset_date' => now()->addDay()->toDateString(), 'status' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors(['condition', 'onset_date', 'status']);
        $this->postJson("/api/v1/patients/{$patient->id}/allergies", ['allergen' => '', 'severity' => 'critical', 'status' => 'active'])
            ->assertUnprocessable()->assertJsonValidationErrors(['allergen', 'severity']);
        $this->assertSame(0, PatientMedicalHistory::count());
        $this->assertSame(0, PatientAllergy::count());
    }

    public function test_doctor_uploads_and_downloads_private_patient_document(): void
    {
        Storage::fake('local');
        $doctor = $this->signIn('doctor@lotus.test');
        $patient = $this->patient();
        $response = $this->postJson("/api/v1/patients/{$patient->id}/documents", ['category' => 'report', 'file' => UploadedFile::fake()->create('safe-report.pdf', 32, 'application/pdf')])
            ->assertCreated()->assertJsonMissingPath('data.path')->assertJsonPath('data.uploaded_by', $doctor->id);
        $document = PatientDocument::findOrFail($response->json('data.id'));
        Storage::disk('local')->assertExists($document->path);

        $this->get("/api/v1/patients/{$patient->id}/documents/{$document->id}")->assertOk()->assertDownload('safe-report.pdf');
        $this->assertSame(2, AuditLog::where('module', 'patient_documents')->count());
    }

    public function test_patient_document_rejects_unsafe_type_and_oversize_file(): void
    {
        Storage::fake('local');
        $this->signIn('doctor@lotus.test');
        $patient = $this->patient();
        $this->postJson("/api/v1/patients/{$patient->id}/documents", ['category' => 'other', 'file' => UploadedFile::fake()->create('script.php', 1, 'text/x-php')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson("/api/v1/patients/{$patient->id}/documents", ['category' => 'other', 'file' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertSame(0, PatientDocument::count());
    }

    public function test_doctor_sees_clinical_screen_with_escaped_content_and_attribution(): void
    {
        $doctor = $this->signIn('doctor@lotus.test');
        $patient = $this->patient();
        $patient->allergies()->create(['hospital_id' => $patient->hospital_id, 'branch_id' => $this->branch()->id, 'recorded_by' => $doctor->id, 'allergen' => '<script>alert(1)</script>', 'severity' => 'unknown', 'status' => 'active']);

        $this->get("/patients/{$patient->id}/clinical-history")->assertOk()->assertSee('Clinical information')
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertSee($doctor->name);
    }

    private function signIn(string $email): User
    {
        $user = User::where('email', $email)->firstOrFail();
        $membership = $user->memberships()->whereHas('branch', fn ($query) => $query->where('code', 'CBE'))->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);

        return $user;
    }

    private function patient(): Patient
    {
        return Patient::factory()->forBranch($this->branch())->create();
    }

    private function branch(string $hospitalCode = 'LOTUS', string $branchCode = 'CBE'): Branch
    {
        $hospital = Hospital::where('code', $hospitalCode)->firstOrFail();

        return Branch::where('hospital_id', $hospital->id)->where('code', $branchCode)->firstOrFail();
    }
}
