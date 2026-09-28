<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PatientRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_guests_cannot_access_patient_screens_or_api_endpoints(): void
    {
        $patient = $this->patient();
        $patientCount = Patient::count();

        foreach (['/patients', '/patients/create', '/patients/'.$patient->id, '/patients/'.$patient->id.'/edit'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
        foreach (['/api/v1/patients', '/api/v1/patients/'.$patient->id, '/api/v1/patients/duplicates?mobile=919001234567'] as $path) {
            $this->getJson($path)->assertUnauthorized();
        }
        $this->postJson('/api/v1/patients', $this->patientPayload())->assertUnauthorized();
        $this->putJson('/api/v1/patients/'.$patient->id, $this->patientPayload())->assertUnauthorized();

        $this->assertSame($patientCount, Patient::count());
        $this->assertDatabaseMissing('audit_logs', ['module' => 'patients']);
    }

    #[DataProvider('patientReaders')]
    public function test_all_pilot_roles_can_view_and_search_hospital_patient_identity(string $email): void
    {
        $patient = $this->patient();
        $this->signIn($email);

        $this->getJson('/api/v1/patients?q='.urlencode($patient->uhid))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $patient->id);
        $this->getJson('/api/v1/patients/'.$patient->id)
            ->assertOk()->assertJsonPath('data.uhid', $patient->uhid);
    }

    /** @return array<string, array{string}> */
    public static function patientReaders(): array
    {
        return [
            'administrator' => ['admin@lotus.test'],
            'receptionist' => ['reception@lotus.test'],
            'doctor' => ['doctor@lotus.test'],
        ];
    }

    public function test_doctor_cannot_register_or_edit_patients_through_html_or_json(): void
    {
        $this->signIn('doctor@lotus.test');
        $patient = $this->patient();
        $original = $patient->getAttributes();
        $patientCount = Patient::count();

        $this->get('/patients/create')->assertForbidden();
        $this->get('/patients/'.$patient->id.'/edit')->assertForbidden();
        $this->post('/patients', $this->patientPayload())->assertForbidden();
        $this->put('/patients/'.$patient->id, $this->patientPayload())->assertForbidden();
        $this->postJson('/api/v1/patients', $this->patientPayload())->assertForbidden();
        $this->putJson('/api/v1/patients/'.$patient->id, $this->patientPayload())->assertForbidden();

        $this->assertSame($patientCount, Patient::count());
        $this->assertSame($original, $patient->fresh()->getAttributes());
        $this->assertDatabaseMissing('audit_logs', ['module' => 'patients']);
        $this->get('/patients/'.$patient->id)->assertDontSee(route('patients.edit', $patient), false);
    }

    public function test_registration_uses_authenticated_identity_normalizes_contacts_and_records_a_safe_audit(): void
    {
        $actor = $this->signIn('reception@lotus.test');
        $foreignHospital = $this->hospital('RIVER');
        $foreignBranch = Branch::where('hospital_id', $foreignHospital->id)->firstOrFail();
        $payload = [
            ...$this->patientPayload(),
            'mobile' => '+91 (900) 123-4567',
            'emergency_contact_mobile' => '900 123 4568',
            'id' => 999999,
            'hospital_id' => $foreignHospital->id,
            'branch_id' => $foreignBranch->id,
            'registered_by' => User::where('email', 'admin@river.test')->value('id'),
            'uhid' => 'FORGED-UHID',
            'password' => 'patient-secret-must-not-be-recorded',
            'clinical_notes' => 'untrusted-clinical-text',
            'private_path' => '/private/example',
        ];

        $response = $this->postJson('/api/v1/patients', $payload)->assertCreated()
            ->assertJsonPath('data.hospital_id', $this->hospital()->id)
            ->assertJsonPath('data.branch_id', $this->branch()->id)
            ->assertJsonPath('data.registered_by', $actor->id)
            ->assertJsonPath('data.mobile', '+919001234567')
            ->assertJsonPath('data.emergency_contact_mobile', '9001234568')
            ->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.clinical_notes');

        $patient = Patient::findOrFail($response->json('data.id'));
        $this->assertNotSame(999999, $patient->id);
        $this->assertNotSame('FORGED-UHID', $patient->uhid);
        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'first_name' => 'Meera', 'mobile' => '+919001234567']);
        $log = AuditLog::where('module', 'patients')->where('record_id', $patient->id)->where('action', 'created')->firstOrFail();
        $this->assertSame($actor->id, $log->user_id);
        $this->assertSame($this->hospital()->id, $log->hospital_id);
        $this->assertSame($this->branch()->id, $log->branch_id);
        $this->assertNull($log->old_values);
        $this->assertSame('Meera', $log->new_values['first_name']);
        $this->assertStringStartsWith('1992-04-15', $log->new_values['date_of_birth']);
        foreach (['password', 'clinical_notes', 'private_path'] as $key) {
            $this->assertArrayNotHasKey($key, $log->new_values);
            $this->assertStringNotContainsString($payload[$key], $log->toJson());
        }
    }

    public function test_another_branch_can_update_demographics_without_changing_registration_provenance(): void
    {
        $originalActor = User::where('email', 'reception@lotus.test')->firstOrFail();
        $patient = $this->patient(['registered_by' => $originalActor->id]);
        $identity = $patient->only(['id', 'hospital_id', 'branch_id', 'registered_by', 'uhid']);
        $actor = $this->signIn('admin@lotus.test', 'CHN');

        $this->getJson('/api/v1/patients/'.$patient->id)->assertOk()->assertJsonPath('data.id', $patient->id);
        $this->putJson('/api/v1/patients/'.$patient->id, [
            ...$this->patientPayload(), 'first_name' => 'Updated Meera',
            'hospital_id' => $this->hospital('RIVER')->id, 'branch_id' => $this->branch('CHN')->id,
            'registered_by' => $actor->id, 'uhid' => 'FORGED-EDIT', 'id' => 999999,
        ])->assertOk()->assertJsonPath('data.first_name', 'Updated Meera');

        $this->assertSame($identity, $patient->fresh()->only(array_keys($identity)));
        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'first_name' => 'Updated Meera']);
        $log = AuditLog::where('module', 'patients')->where('record_id', $patient->id)->where('action', 'updated')->firstOrFail();
        $this->assertSame($this->branch('CHN')->id, $log->branch_id);
        $this->assertSame($actor->id, $log->user_id);
        $this->assertSame('Meera', $log->old_values['first_name']);
        $this->assertSame('Updated Meera', $log->new_values['first_name']);
    }

    public function test_foreign_patient_ids_are_hidden_on_all_read_and_update_routes(): void
    {
        $foreignBranch = Branch::where('hospital_id', $this->hospital('RIVER')->id)->firstOrFail();
        $patient = Patient::factory()->forBranch($foreignBranch)->create($this->patientPayload())->refresh();
        $original = $patient->getAttributes();
        $this->signIn();

        $this->getJson('/api/v1/patients/'.$patient->id)->assertNotFound();
        $this->putJson('/api/v1/patients/'.$patient->id, [...$this->patientPayload(), 'first_name' => 'Forbidden change'])->assertNotFound();
        $this->get('/patients/'.$patient->id)->assertNotFound();
        $this->get('/patients/'.$patient->id.'/edit')->assertNotFound();
        $this->put('/patients/'.$patient->id, $this->patientPayload())->assertNotFound();

        $this->assertSame($original, $patient->fresh()->getAttributes());
        $this->assertDatabaseMissing('audit_logs', ['module' => 'patients']);
    }

    public function test_revoked_membership_cannot_continue_patient_access_or_write(): void
    {
        $user = $this->signIn('reception@lotus.test');
        $patientCount = Patient::count();
        $this->getJson('/api/v1/patients')->assertOk();
        $user->memberships()->where('branch_id', $this->branch()->id)->update(['status' => 'inactive']);

        $this->getJson('/api/v1/patients')->assertForbidden();
        $this->getJson('/api/v1/patients/duplicates?mobile=919001234567')->assertForbidden();
        $this->postJson('/api/v1/patients', $this->patientPayload())->assertForbidden();

        $this->assertSame($patientCount, Patient::count());
    }

    public function test_revoked_patient_permissions_are_checked_on_the_next_request(): void
    {
        $this->signIn('reception@lotus.test');
        $patient = $this->patient();
        $this->getJson('/api/v1/patients/'.$patient->id)->assertOk();
        $role = Role::where('name', 'RECEPTIONIST')->firstOrFail();
        $role->permissions()->detach(Permission::whereIn('name', ['PATIENT.VIEW', 'PATIENT.MANAGE'])->pluck('id'));

        $this->getJson('/api/v1/patients/'.$patient->id)->assertForbidden();
        $this->getJson('/api/v1/patients/duplicates?mobile=919001234567')->assertForbidden();
        $this->postJson('/api/v1/patients', $this->patientPayload())->assertForbidden();
        $this->putJson('/api/v1/patients/'.$patient->id, [...$this->patientPayload(), 'first_name' => 'Denied'])->assertForbidden();

        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'first_name' => 'Meera']);
        $this->assertDatabaseMissing('audit_logs', ['module' => 'patients']);
    }

    public function test_search_matches_uhid_name_and_mobile_but_never_other_hospitals(): void
    {
        $patient = $this->patient();
        $foreignBranch = Branch::where('hospital_id', $this->hospital('RIVER')->id)->firstOrFail();
        $foreign = Patient::factory()->forBranch($foreignBranch)->create($this->patientPayload());
        $this->signIn('reception@lotus.test');

        foreach ([$patient->uhid, 'Meera', 'Sundaram', 'Meera Sundaram', '919001234567'] as $query) {
            $this->getJson('/api/v1/patients?'.http_build_query(['q' => $query]))
                ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $patient->id);
        }
        $this->getJson('/api/v1/patients?q='.urlencode($foreign->uhid))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/patients?q='.urlencode("' OR 1=1 --"))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_status_filter_and_pagination_preserve_hospital_scope(): void
    {
        $patient = $this->patient(['status' => 'inactive']);
        $this->patient(['first_name' => 'Active Meera', 'mobile' => '919001234568']);
        $foreignBranch = Branch::where('hospital_id', $this->hospital('RIVER')->id)->firstOrFail();
        Patient::factory()->forBranch($foreignBranch)->create([...$this->patientPayload(), 'status' => 'inactive']);
        $this->signIn();

        $this->getJson('/api/v1/patients?status=inactive&q=Meera')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $patient->id);
        $this->getJson('/api/v1/patients?status=active&q=Meera')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.first_name', 'Active Meera');
        $response = $this->getJson('/api/v1/patients')->assertOk();
        foreach ($response->json('data') as $record) {
            $this->assertSame($this->hospital()->id, $record['hospital_id']);
        }
        $response->assertJsonStructure(['data', 'current_page', 'last_page', 'total']);
    }

    public function test_duplicate_preview_matches_normalized_phone_or_email_and_returns_minimal_demographics(): void
    {
        $patient = $this->patient(['mobile' => '+919001234567', 'address' => 'Confidential home address', 'emergency_contact_name' => 'Private contact']);
        $foreignBranch = Branch::where('hospital_id', $this->hospital('RIVER')->id)->firstOrFail();
        Patient::factory()->forBranch($foreignBranch)->create([...$this->patientPayload(), 'mobile' => '+919001234567']);
        $this->signIn('reception@lotus.test');

        foreach ([['mobile' => '+91 (900) 123-4567'], ['email' => 'meera.patient@example.test']] as $query) {
            $this->getJson('/api/v1/patients/duplicates?'.http_build_query($query))
                ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $patient->id)
                ->assertJsonPath('data.0.uhid', $patient->uhid)
                ->assertJsonMissingPath('data.0.address')->assertJsonMissingPath('data.0.emergency_contact_name')
                ->assertJsonMissingPath('data.0.registered_by');
        }
    }

    public function test_duplicate_preview_distinguishes_known_birth_dates_and_supports_unknown_dates(): void
    {
        $patient = $this->patient();
        $this->patient(['date_of_birth' => '1990-11-20', 'mobile' => '919001234568', 'email' => 'other.patient@example.test']);
        $this->signIn();

        $this->getJson('/api/v1/patients/duplicates?'.http_build_query([
            'first_name' => 'Meera', 'last_name' => 'Sundaram', 'date_of_birth' => '1992-04-15',
        ]))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $patient->id);
        $this->getJson('/api/v1/patients/duplicates?'.http_build_query([
            'first_name' => 'Meera', 'last_name' => 'Sundaram',
        ]))->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/patients/duplicates')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_shared_emergency_and_primary_contact_numbers_suggest_duplicates_before_registration(): void
    {
        $patient = $this->patient(['mobile' => null, 'email' => null, 'emergency_contact_mobile' => '+91 (900) 123-4568']);
        $this->signIn('reception@lotus.test');
        $patientCount = Patient::count();

        foreach (['mobile', 'emergency_contact_mobile'] as $field) {
            $this->getJson('/api/v1/patients/duplicates?'.http_build_query([$field => '919001234568']))
                ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $patient->id);
        }
        $this->postJson('/api/v1/patients', [
            ...$this->patientPayload(), 'first_name' => 'Other Family Member',
            'mobile' => null, 'email' => null, 'emergency_contact_mobile' => '919001234568',
        ])->assertUnprocessable()->assertJsonValidationErrors('duplicates_confirmed')->assertJsonPath('duplicates.0.id', $patient->id);

        $this->assertSame($patientCount, Patient::count());
        $this->assertDatabaseMissing('audit_logs', ['module' => 'patients']);
    }

    public function test_emergency_contact_preview_matches_an_existing_primary_mobile(): void
    {
        $patient = $this->patient();
        $this->signIn('reception@lotus.test');

        $this->getJson('/api/v1/patients/duplicates?'.http_build_query(['emergency_contact_mobile' => '+91 (900) 123-4567']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $patient->id);
    }

    public function test_malformed_search_and_duplicate_queries_return_validation_errors(): void
    {
        $this->signIn('reception@lotus.test');

        $this->getJson('/api/v1/patients?'.http_build_query(['q' => ['unexpected']]))
            ->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->getJson('/api/v1/patients?status=invalid')
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->getJson('/api/v1/patients/duplicates?'.http_build_query(['mobile' => ['919001234567']]))
            ->assertUnprocessable()->assertJsonValidationErrors('mobile');
        $this->getJson('/api/v1/patients/duplicates?'.http_build_query(['emergency_contact_mobile' => 'invalid']))
            ->assertUnprocessable()->assertJsonValidationErrors('emergency_contact_mobile');
    }

    public function test_duplicate_preview_is_bounded_and_ignores_submitted_hospital_scope(): void
    {
        Patient::factory()->count(12)->forBranch($this->branch())->create(['mobile' => '8881234567']);
        $this->signIn();

        $response = $this->getJson('/api/v1/patients/duplicates?'.http_build_query([
            'mobile' => '8881234567', 'hospital_id' => $this->hospital('RIVER')->id,
        ]))->assertOk()->assertJsonCount(10, 'data');

        foreach ($response->json('data') as $record) {
            $this->assertDatabaseHas('patients', ['id' => $record['id'], 'hospital_id' => $this->hospital()->id]);
        }
    }

    public function test_duplicate_registration_requires_review_without_consuming_patient_identity(): void
    {
        $existing = $this->patient();
        $this->signIn('reception@lotus.test');
        $patientCount = Patient::count();
        $sequences = DB::table('patient_number_sequences')->orderBy('hospital_id')->get()->toJson();
        $payload = [...$this->patientPayload(), 'first_name' => 'Family member', 'date_of_birth' => '2005-01-10'];

        $this->postJson('/api/v1/patients', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('duplicates_confirmed')
            ->assertJsonPath('duplicates.0.id', $existing->id);

        $this->assertSame($patientCount, Patient::count());
        $this->assertSame($sequences, DB::table('patient_number_sequences')->orderBy('hospital_id')->get()->toJson());
        $this->assertDatabaseMissing('audit_logs', ['module' => 'patients']);

        $response = $this->postJson('/api/v1/patients', [...$payload, 'duplicates_confirmed' => true])
            ->assertCreated()->assertJsonPath('data.first_name', 'Family member');
        $this->assertNotSame($existing->id, $response->json('data.id'));
        $this->assertNotSame($existing->uhid, $response->json('data.uhid'));
        $this->assertSame(2, Patient::where('hospital_id', $this->hospital()->id)->where('mobile', '919001234567')->count());
    }

    public function test_foreign_contact_matches_do_not_block_registration_or_leak_duplicate_details(): void
    {
        $foreignBranch = Branch::where('hospital_id', $this->hospital('RIVER')->id)->firstOrFail();
        Patient::factory()->forBranch($foreignBranch)->create($this->patientPayload());
        $this->signIn('reception@lotus.test');

        $this->getJson('/api/v1/patients/duplicates?mobile=919001234567')->assertOk()->assertJsonCount(0, 'data');
        $response = $this->postJson('/api/v1/patients', $this->patientPayload())
            ->assertCreated()->assertJsonPath('data.hospital_id', $this->hospital()->id);

        $this->assertDatabaseHas('patients', ['id' => $response->json('data.id'), 'hospital_id' => $this->hospital()->id]);
    }

    public function test_registration_requires_demographics_and_a_usable_contact(): void
    {
        $this->signIn('reception@lotus.test');
        $patientCount = Patient::count();

        $this->postJson('/api/v1/patients', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'date_of_birth', 'gender', 'mobile']);

        $this->assertSame($patientCount, Patient::count());
        $this->assertDatabaseMissing('audit_logs', ['module' => 'patients']);
    }

    #[DataProvider('invalidDemographics')]
    public function test_invalid_demographics_return_validation_errors_without_writes(array $overrides, array $errors): void
    {
        $this->signIn('reception@lotus.test');
        $patientCount = Patient::count();

        $this->postJson('/api/v1/patients', [...$this->patientPayload(), ...$overrides])
            ->assertUnprocessable()->assertJsonValidationErrors($errors);

        $this->assertSame($patientCount, Patient::count());
        $this->assertDatabaseMissing('audit_logs', ['module' => 'patients']);
    }

    /** @return array<string, array{array<string, mixed>, list<string>}> */
    public static function invalidDemographics(): array
    {
        return [
            'mobile letters' => [['mobile' => 'not-a-phone'], ['mobile']],
            'mobile too short' => [['mobile' => '123456'], ['mobile']],
            'mobile too long' => [['mobile' => '1234567890123456'], ['mobile']],
            'mobile array' => [['mobile' => ['919001234567']], ['mobile']],
            'emergency mobile letters' => [['emergency_contact_mobile' => 'unknown'], ['emergency_contact_mobile']],
            'missing all contact methods' => [['mobile' => null, 'email' => null, 'emergency_contact_mobile' => null], ['mobile']],
            'date supplied as unknown' => [['date_of_birth_unknown' => true], ['date_of_birth']],
            'invalid calendar date' => [['date_of_birth' => '1992-02-30'], ['date_of_birth']],
            'unsupported gender' => [['gender' => 'invalid'], ['gender']],
            'invalid email' => [['email' => 'invalid-email'], ['email']],
        ];
    }

    public function test_unknown_birth_date_can_be_registered_then_corrected_and_cleared_with_audit(): void
    {
        $this->signIn('reception@lotus.test');
        $payload = [...$this->patientPayload(), 'date_of_birth_unknown' => true, 'date_of_birth' => null, 'mobile' => null, 'email' => null, 'emergency_contact_mobile' => '900 123 4568'];

        $response = $this->postJson('/api/v1/patients', $payload)->assertCreated()
            ->assertJsonPath('data.date_of_birth_unknown', true)->assertJsonPath('data.date_of_birth', null);
        $patientId = $response->json('data.id');
        $this->putJson('/api/v1/patients/'.$patientId, $this->patientPayload())->assertOk()
            ->assertJsonPath('data.date_of_birth_unknown', false);
        $this->assertDatabaseHas('patients', ['id' => $patientId, 'date_of_birth' => '1992-04-15 00:00:00']);
        $this->putJson('/api/v1/patients/'.$patientId, $payload)->assertOk()
            ->assertJsonPath('data.date_of_birth_unknown', true)->assertJsonPath('data.date_of_birth', null);

        $this->assertDatabaseHas('patients', ['id' => $patientId, 'date_of_birth' => null, 'date_of_birth_unknown' => true]);
        $logs = AuditLog::where('module', 'patients')->where('record_id', $patientId)->where('action', 'updated')->orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertTrue($logs[0]->old_values['date_of_birth_unknown']);
        $this->assertFalse($logs[0]->new_values['date_of_birth_unknown']);
        $this->assertStringStartsWith('1992-04-15', $logs[1]->old_values['date_of_birth']);
        $this->assertNull($logs[1]->new_values['date_of_birth']);
    }

    public function test_invalid_update_keeps_existing_demographics_and_creates_no_audit(): void
    {
        $this->signIn('reception@lotus.test');
        $patient = $this->patient();
        $original = $patient->getAttributes();

        $this->putJson('/api/v1/patients/'.$patient->id, [...$this->patientPayload(), 'mobile' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('mobile');

        $this->assertSame($original, $patient->fresh()->getAttributes());
        $this->assertDatabaseMissing('audit_logs', ['module' => 'patients']);
    }

    public function test_unchanged_demographic_update_does_not_create_a_false_change_audit(): void
    {
        $this->signIn('reception@lotus.test');
        $patient = $this->patient();
        $original = $patient->getAttributes();

        $this->putJson('/api/v1/patients/'.$patient->id, $this->patientPayload())
            ->assertOk()->assertJsonPath('data.id', $patient->id);

        $this->assertSame($original, $patient->fresh()->getAttributes());
        $this->assertDatabaseMissing('audit_logs', ['module' => 'patients']);
    }

    public function test_failed_registration_audit_rolls_back_patient_and_uhid_sequence(): void
    {
        $this->signIn('reception@lotus.test');
        $patientCount = Patient::count();
        $sequences = DB::table('patient_number_sequences')->orderBy('hospital_id')->get()->toJson();
        $this->mock(AuditService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('Audit unavailable'));
        });
        $this->withoutExceptionHandling();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Audit unavailable');

        try {
            $this->postJson('/api/v1/patients', $this->patientPayload());
        } finally {
            $this->assertSame($patientCount, Patient::count());
            $this->assertSame($sequences, DB::table('patient_number_sequences')->orderBy('hospital_id')->get()->toJson());
            $this->assertDatabaseMissing('patients', ['email' => 'meera.patient@example.test']);
            $this->assertDatabaseMissing('audit_logs', ['module' => 'patients']);
        }
    }

    public function test_failed_update_audit_rolls_back_demographic_changes(): void
    {
        $this->signIn('reception@lotus.test');
        $patient = $this->patient();
        $original = $patient->getAttributes();
        $this->mock(AuditService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('Audit unavailable'));
        });
        $this->withoutExceptionHandling();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Audit unavailable');

        try {
            $this->putJson('/api/v1/patients/'.$patient->id, [...$this->patientPayload(), 'first_name' => 'Rolled back']);
        } finally {
            $this->assertSame($original, $patient->fresh()->getAttributes());
            $this->assertDatabaseMissing('audit_logs', ['module' => 'patients']);
        }
    }

    public function test_browser_registration_duplicate_review_and_edit_flow_persists_changes(): void
    {
        $this->signIn('reception@lotus.test');
        $this->get('/patients/create')->assertSee('name="first_name"', false)->assertSee('name="date_of_birth_unknown"', false);
        $this->from('/patients/create')->post('/patients', $this->patientPayload())->assertRedirect();
        $patient = Patient::where('email', 'meera.patient@example.test')->firstOrFail();
        $this->get('/patients/'.$patient->id)->assertSee($patient->uhid)->assertSee('Meera');
        $patientCount = Patient::count();

        $this->from('/patients/create')->post('/patients', $this->patientPayload())
            ->assertRedirect('/patients/create')->assertSessionHasErrors('duplicates_confirmed')->assertSessionHas('duplicates');
        $this->assertSame($patientCount, Patient::count());
        $this->get('/patients/create')->assertSee($patient->uhid)->assertSee('name="duplicates_confirmed"', false);
        $this->get('/patients/'.$patient->id.'/edit')->assertSee('Meera');
        $this->put('/patients/'.$patient->id, [...$this->patientPayload(), 'city' => 'Chennai'])->assertRedirect();

        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'city' => 'Chennai']);
    }

    public function test_patient_screens_escape_demographic_html(): void
    {
        $this->signIn('reception@lotus.test');
        $patient = $this->patient(['first_name' => '<script>alert("name")</script>', 'address' => '<img src=x onerror=alert(1)>']);

        foreach (['/patients', '/patients/'.$patient->id, '/patients/'.$patient->id.'/edit'] as $path) {
            $this->get($path)->assertSee('&lt;script&gt;', false)
                ->assertDontSee('<script>alert("name")</script>', false)->assertDontSee('<img src=x onerror=alert(1)>', false);
        }
    }

    public function test_administrator_can_review_escaped_patient_changes_in_activity_log(): void
    {
        $this->signIn();
        $patient = $this->patient(['first_name' => 'Before']);
        $this->putJson('/api/v1/patients/'.$patient->id, [
            ...$this->patientPayload(), 'first_name' => '<script>alert("audit")</script>',
        ])->assertOk();

        $this->get('/audit')->assertOk()
            ->assertSee('View 1 changed field')
            ->assertSee('Before')
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert("audit")</script>', false);

        $this->signIn('reception@lotus.test');
        $this->get('/audit')->assertForbidden();
    }

    private function signIn(string $email = 'admin@lotus.test', string $branchCode = 'CBE'): User
    {
        $user = User::where('email', $email)->firstOrFail();
        $membership = $user->memberships()->whereHas('branch', fn ($query) => $query->where('code', $branchCode))->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);

        return $user;
    }

    private function hospital(string $code = 'LOTUS'): Hospital
    {
        return Hospital::where('code', $code)->firstOrFail();
    }

    private function branch(string $code = 'CBE'): Branch
    {
        return Branch::where('hospital_id', $this->hospital()->id)->where('code', $code)->firstOrFail();
    }

    /** @param array<string, mixed> $overrides */
    private function patient(array $overrides = []): Patient
    {
        return Patient::factory()->forBranch($this->branch())->create([...$this->patientPayload(), ...$overrides])->refresh();
    }

    /**
     * @return array{first_name: string, last_name: string, date_of_birth: string, date_of_birth_unknown: bool, gender: string, mobile: string, email: string, status: string}
     */
    private function patientPayload(): array
    {
        return [
            'first_name' => 'Meera', 'last_name' => 'Sundaram', 'date_of_birth' => '1992-04-15',
            'date_of_birth_unknown' => false, 'gender' => 'female', 'mobile' => '919001234567',
            'email' => 'meera.patient@example.test', 'status' => 'active',
        ];
    }
}
