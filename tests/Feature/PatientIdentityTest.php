<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Services\PatientIdentityService;
use Database\Seeders\PatientSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PatientIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-09-13 09:00:00', 'Asia/Kolkata'));
    }

    public function test_registration_preserves_demographics_and_hospital_branch_and_actor_relationships(): void
    {
        $branch = Branch::factory()->create();
        $actor = User::factory()->create(['hospital_id' => $branch->hospital_id, 'status' => 'active']);
        $demographics = [
            ...$this->demographics(),
            'last_name' => 'Patient',
            'email' => 'synthetic.patient@example.test',
            'blood_group' => 'O+',
            'address' => 'Synthetic test address',
            'city' => 'Coimbatore',
            'state' => 'Tamil Nadu',
            'pincode' => '641001',
            'emergency_contact_name' => 'Synthetic guardian',
            'emergency_contact_mobile' => '9876543211',
        ];

        $patient = $this->service()->create($branch->hospital, $branch, $demographics, $actor)->fresh();

        $this->assertSame('HSP-'.$branch->hospital_id.'-2026-000001', $patient->uhid);
        $this->assertTrue($patient->hospital->is($branch->hospital));
        $this->assertTrue($patient->branch->is($branch));
        $this->assertTrue($patient->registeredBy->is($actor));
        $this->assertSame('1990-05-20', $patient->date_of_birth->toDateString());
        $this->assertFalse($patient->date_of_birth_unknown);
        $this->assertSame('active', $patient->status);
        foreach ($demographics as $field => $value) {
            if ($field !== 'date_of_birth') {
                $this->assertSame($value, $patient->getAttribute($field), $field);
            }
        }
    }

    public function test_branches_share_the_hospital_sequence_and_other_hospitals_have_independent_sequences(): void
    {
        $firstBranch = Branch::factory()->create();
        $secondBranch = Branch::factory()->create(['hospital_id' => $firstBranch->hospital_id]);
        $foreignBranch = Branch::factory()->create();

        $firstPatient = $this->register($firstBranch);
        $secondPatient = $this->register($secondBranch);
        $foreignPatient = $this->register($foreignBranch);
        $thirdPatient = $this->register($firstBranch);

        $this->assertSame('HSP-'.$firstBranch->hospital_id.'-2026-000001', $firstPatient->uhid);
        $this->assertSame('HSP-'.$firstBranch->hospital_id.'-2026-000002', $secondPatient->uhid);
        $this->assertSame('HSP-'.$firstBranch->hospital_id.'-2026-000003', $thirdPatient->uhid);
        $this->assertSame('HSP-'.$foreignBranch->hospital_id.'-2026-000001', $foreignPatient->uhid);
        $this->assertSame($secondBranch->id, $secondPatient->branch_id);
        $this->assertDatabaseHas('patient_number_sequences', ['hospital_id' => $firstBranch->hospital_id, 'year' => 2026, 'last_number' => 3]);
        $this->assertDatabaseHas('patient_number_sequences', ['hospital_id' => $foreignBranch->hospital_id, 'year' => 2026, 'last_number' => 1]);
    }

    public function test_uhid_survives_hospital_code_changes(): void
    {
        $branch = Branch::factory()->create();
        $firstPatient = $this->register($branch);
        $branch->hospital->update(['code' => 'RENAMED']);

        $secondPatient = $this->register($branch);

        $this->assertSame($firstPatient->uhid, $firstPatient->fresh()->uhid);
        $this->assertSame('HSP-'.$branch->hospital_id.'-2026-000002', $secondPatient->uhid);
    }

    public function test_sequence_rolls_over_at_indian_midnight_and_preserves_previous_identifiers(): void
    {
        $branch = Branch::factory()->create();
        $this->travelTo(Carbon::parse('2026-12-31 18:29:59', 'UTC'));
        $lastPatientOfYear = $this->register($branch);

        $this->travelTo(Carbon::parse('2026-12-31 18:30:00', 'UTC'));
        $firstPatientOfYear = $this->register($branch);
        $secondPatientOfYear = $this->register($branch);

        $this->assertSame('HSP-'.$branch->hospital_id.'-2026-000001', $lastPatientOfYear->fresh()->uhid);
        $this->assertSame('HSP-'.$branch->hospital_id.'-2027-000001', $firstPatientOfYear->uhid);
        $this->assertSame('HSP-'.$branch->hospital_id.'-2027-000002', $secondPatientOfYear->uhid);
        $this->assertDatabaseCount('patient_number_sequences', 2);
    }

    public function test_number_padding_does_not_truncate_numbers_above_six_digits(): void
    {
        $branch = Branch::factory()->create();
        $this->register($branch);
        DB::table('patient_number_sequences')->where('hospital_id', $branch->hospital_id)->update(['last_number' => 999999]);

        $this->assertSame('HSP-'.$branch->hospital_id.'-2026-1000000', $this->register($branch)->uhid);
    }

    public function test_failed_patient_insert_rolls_back_the_allocated_number(): void
    {
        $branch = Branch::factory()->create();
        $this->register($branch);
        $dispatcher = Patient::getEventDispatcher();
        Patient::setEventDispatcher(clone $dispatcher);

        try {
            Patient::creating(function (Patient $patient): void {
                throw new RuntimeException('Synthetic insert failure');
            });

            try {
                $this->register($branch);
                $this->fail('The simulated patient insertion failure should propagate.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Synthetic insert failure', $exception->getMessage());
            }
        } finally {
            Patient::setEventDispatcher($dispatcher);
        }

        $this->assertDatabaseCount('patients', 1);
        $this->assertDatabaseHas('patient_number_sequences', ['hospital_id' => $branch->hospital_id, 'year' => 2026, 'last_number' => 1]);
        $this->assertSame('HSP-'.$branch->hospital_id.'-2026-000002', $this->register($branch)->uhid);
    }

    public function test_outer_transaction_rollback_removes_patient_and_new_sequence_together(): void
    {
        $branch = Branch::factory()->create();

        try {
            DB::transaction(function () use ($branch): void {
                $this->register($branch);
                throw new RuntimeException('Synthetic enclosing workflow failure');
            });
            $this->fail('The enclosing workflow failure should propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic enclosing workflow failure', $exception->getMessage());
        }

        $this->assertDatabaseCount('patients', 0);
        $this->assertDatabaseCount('patient_number_sequences', 0);
        $this->assertSame('HSP-'.$branch->hospital_id.'-2026-000001', $this->register($branch)->uhid);
    }

    public function test_cancelled_patient_insert_rolls_back_the_allocated_number(): void
    {
        $branch = Branch::factory()->create();
        $dispatcher = Patient::getEventDispatcher();
        Patient::setEventDispatcher(clone $dispatcher);

        try {
            Patient::creating(fn (Patient $patient): bool => false);

            try {
                $this->register($branch);
                $this->fail('A cancelled insert must fail registration.');
            } catch (LogicException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        } finally {
            Patient::setEventDispatcher($dispatcher);
        }

        $this->assertDatabaseCount('patients', 0);
        $this->assertDatabaseCount('patient_number_sequences', 0);
        $this->assertSame('HSP-'.$branch->hospital_id.'-2026-000001', $this->register($branch)->uhid);
    }

    public function test_registration_rejects_a_branch_from_another_hospital(): void
    {
        $hospital = Hospital::factory()->create();
        $foreignBranch = Branch::factory()->create();

        $this->assertValidationFailure(fn () => $this->service()->create($hospital, $foreignBranch, $this->demographics()), 'branch_id');
        $this->assertDatabaseCount('patients', 0);
        $this->assertDatabaseCount('patient_number_sequences', 0);
    }

    public function test_registration_rechecks_branch_status_instead_of_trusting_a_stale_model(): void
    {
        $branch = Branch::factory()->create();
        Branch::whereKey($branch->id)->update(['status' => 'inactive']);

        $this->assertValidationFailure(fn () => $this->register($branch), 'branch_id');
        $this->assertDatabaseCount('patients', 0);
        $this->assertDatabaseCount('patient_number_sequences', 0);
    }

    public function test_registration_rechecks_hospital_status_instead_of_trusting_a_stale_model(): void
    {
        $branch = Branch::factory()->create();
        $hospital = $branch->hospital;
        Hospital::whereKey($hospital->id)->update(['status' => 'inactive']);

        $this->assertValidationFailure(fn () => $this->service()->create($hospital, $branch, $this->demographics()), 'hospital_id');
        $this->assertDatabaseCount('patients', 0);
    }

    public function test_registration_rejects_an_actor_from_another_hospital(): void
    {
        $branch = Branch::factory()->create();
        $foreignActor = User::factory()->create(['status' => 'active']);

        $this->assertValidationFailure(fn () => $this->service()->create($branch->hospital, $branch, $this->demographics(), $foreignActor), 'registered_by');
        $this->assertDatabaseCount('patients', 0);
    }

    public function test_registration_rechecks_actor_status_instead_of_trusting_a_stale_model(): void
    {
        $branch = Branch::factory()->create();
        $actor = User::factory()->create(['hospital_id' => $branch->hospital_id, 'status' => 'active']);
        User::whereKey($actor->id)->update(['status' => 'inactive']);

        $this->assertValidationFailure(fn () => $this->service()->create($branch->hospital, $branch, $this->demographics(), $actor), 'registered_by');
        $this->assertDatabaseCount('patients', 0);
    }

    public function test_registration_supports_explicitly_unknown_birth_date_and_an_emergency_contact(): void
    {
        $branch = Branch::factory()->create();
        $patient = $this->register($branch, ['date_of_birth' => null, 'date_of_birth_unknown' => true, 'mobile' => null, 'emergency_contact_mobile' => '9876543211'])->fresh();

        $this->assertNull($patient->date_of_birth);
        $this->assertTrue($patient->date_of_birth_unknown);
        $this->assertNull($patient->registered_by);
        $this->assertSame('9876543211', $patient->emergency_contact_mobile);
    }

    public function test_shared_contact_details_do_not_make_distinct_patients_duplicates(): void
    {
        $branch = Branch::factory()->create();
        $contact = ['mobile' => '9876543210', 'email' => 'family@example.test'];

        $firstPatient = $this->register($branch, ['first_name' => 'Synthetic parent', ...$contact]);
        $secondPatient = $this->register($branch, ['first_name' => 'Synthetic child', ...$contact]);

        $this->assertNotSame($firstPatient->id, $secondPatient->id);
        $this->assertNotSame($firstPatient->uhid, $secondPatient->uhid);
        $this->assertDatabaseCount('patients', 2);
    }

    public function test_email_alone_satisfies_the_contact_requirement(): void
    {
        $branch = Branch::factory()->create();

        $patient = $this->register($branch, ['mobile' => null, 'email' => 'synthetic@example.test']);

        $this->assertSame('synthetic@example.test', $patient->email);
        $this->assertNull($patient->mobile);
    }

    /**
     * @param  array<string, mixed>  $invalidAttributes
     */
    #[DataProvider('invalidDemographics')]
    public function test_invalid_demographics_are_rejected_without_consuming_a_number(array $invalidAttributes, string $errorField): void
    {
        $branch = Branch::factory()->create();

        $this->assertValidationFailure(fn () => $this->register($branch, $invalidAttributes), $errorField);

        $this->assertDatabaseCount('patients', 0);
        $this->assertDatabaseCount('patient_number_sequences', 0);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidDemographics(): array
    {
        return [
            'missing first name' => [['first_name' => ''], 'first_name'],
            'missing gender' => [['gender' => null], 'gender'],
            'unsupported gender' => [['gender' => 'invalid'], 'gender'],
            'missing birth date without unknown flag' => [['date_of_birth' => null], 'date_of_birth'],
            'future birth date' => [['date_of_birth' => '2026-09-14'], 'date_of_birth'],
            'invalid birth date' => [['date_of_birth' => '2026-02-30'], 'date_of_birth'],
            'contradictory known and unknown birth date' => [['date_of_birth_unknown' => true], 'date_of_birth'],
            'invalid email' => [['email' => 'not-an-email'], 'email'],
            'missing all contact details' => [['mobile' => null, 'email' => null, 'emergency_contact_mobile' => null], 'mobile'],
            'invalid blood group' => [['blood_group' => 'Z+'], 'blood_group'],
            'invalid patient status' => [['status' => 'deleted'], 'status'],
        ];
    }

    #[DataProvider('reservedIdentityFields')]
    public function test_caller_cannot_supply_identity_fields_even_when_the_value_is_null(string $field): void
    {
        $branch = Branch::factory()->create();

        foreach ([null, 12345] as $spoofedValue) {
            $this->assertValidationFailure(fn () => $this->register($branch, [$field => $spoofedValue]), $field);
        }

        $this->assertDatabaseCount('patients', 0);
        $this->assertDatabaseCount('patient_number_sequences', 0);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function reservedIdentityFields(): array
    {
        return [
            'primary key' => ['id'],
            'hospital ownership' => ['hospital_id'],
            'registration branch' => ['branch_id'],
            'public identifier' => ['uhid'],
            'registration actor' => ['registered_by'],
        ];
    }

    public function test_hospital_scope_includes_its_branches_and_excludes_foreign_patients(): void
    {
        $branch = Branch::factory()->create();
        $secondBranch = Branch::factory()->create(['hospital_id' => $branch->hospital_id]);
        $firstPatient = Patient::factory()->forBranch($branch)->create();
        $secondPatient = Patient::factory()->forBranch($secondBranch)->create();
        Patient::factory()->create();

        $this->assertSame([$firstPatient->id, $secondPatient->id], Patient::forHospital($branch->hospital_id)->orderBy('id')->pluck('id')->all());
    }

    public function test_patient_demographics_can_change_without_changing_the_registration_identity(): void
    {
        $patient = Patient::factory()->create();
        $identity = $patient->only(['id', 'hospital_id', 'branch_id', 'uhid', 'registered_by']);

        $patient->update(['first_name' => 'Corrected synthetic name', 'mobile' => '9876543222', 'status' => 'inactive']);

        $this->assertSame($identity, $patient->fresh()->only(array_keys($identity)));
        $this->assertSame('Corrected synthetic name', $patient->fresh()->first_name);
        $this->assertSame('9876543222', $patient->fresh()->mobile);
        $this->assertSame('inactive', $patient->fresh()->status);
    }

    #[DataProvider('reservedIdentityFields')]
    public function test_saved_identity_cannot_be_reassigned_through_the_model(string $field): void
    {
        $patient = Patient::factory()->create()->refresh();
        $original = $patient->getAttributes();
        $patient->setAttribute($field, $field === 'uhid' ? 'REPLACED-UHID' : $patient->getAttribute($field) + 100000);

        try {
            $patient->save();
            $this->fail('Changing '.$field.' should have been rejected.');
        } catch (LogicException) {
            $this->assertSame($original, Patient::findOrFail($original['id'])->getAttributes());
        }
    }

    public function test_mass_assignment_does_not_allow_identity_fields(): void
    {
        $patient = new Patient;
        $patient->fill(['id' => 100000, 'hospital_id' => 100000, 'branch_id' => 100000, 'uhid' => 'SPOOFED-UHID', 'registered_by' => 100000, 'first_name' => 'Synthetic patient']);

        foreach (self::reservedIdentityFields() as [$field]) {
            $this->assertNull($patient->getAttribute($field), $field);
        }
        $this->assertSame('Synthetic patient', $patient->first_name);
    }

    public function test_database_rejects_patient_branch_ownership_mismatch_when_service_is_bypassed(): void
    {
        $patient = Patient::factory()->create();
        $foreignBranch = Branch::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('patients')->where('id', $patient->id)->update(['branch_id' => $foreignBranch->id]);
    }

    public function test_database_rejects_patient_actor_ownership_mismatch_when_service_is_bypassed(): void
    {
        $patient = Patient::factory()->create();
        $foreignActor = User::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('patients')->where('id', $patient->id)->update(['registered_by' => $foreignActor->id]);
    }

    public function test_database_rejects_a_duplicate_uhid_within_a_hospital(): void
    {
        $branch = Branch::factory()->create();
        $firstPatient = $this->register($branch);
        $secondPatient = $this->register($branch);

        $this->expectException(QueryException::class);

        DB::table('patients')->where('id', $secondPatient->id)->update(['uhid' => $firstPatient->uhid]);
    }

    public function test_registration_branch_cannot_be_deleted_while_its_patients_exist(): void
    {
        $patient = Patient::factory()->create();

        $this->expectException(QueryException::class);

        $patient->branch->delete();
    }

    public function test_registration_actor_cannot_be_deleted_while_attributed_patients_exist(): void
    {
        $branch = Branch::factory()->create();
        $actor = User::factory()->create(['hospital_id' => $branch->hospital_id, 'status' => 'active']);
        $this->service()->create($branch->hospital, $branch, $this->demographics(), $actor);

        $this->expectException(QueryException::class);

        $actor->delete();
    }

    public function test_demo_patient_seeding_is_repeatable_and_uses_independent_hospital_sequences(): void
    {
        $firstHospital = Hospital::factory()->create(['code' => 'LOTUS']);
        $secondHospital = Hospital::factory()->create(['code' => 'RIVER']);
        $firstBranch = Branch::factory()->create(['hospital_id' => $firstHospital->id, 'code' => 'CBE']);
        $secondBranch = Branch::factory()->create(['hospital_id' => $secondHospital->id, 'code' => 'SLM']);

        $this->seed(PatientSeeder::class);
        $originalPatients = Patient::orderBy('id')->get()->toArray();
        $this->seed(PatientSeeder::class);

        $this->assertDatabaseCount('patients', 2);
        $this->assertSame($originalPatients, Patient::orderBy('id')->get()->toArray());
        foreach ([$firstBranch, $secondBranch] as $branch) {
            $this->assertDatabaseHas('patients', ['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'uhid' => 'HSP-'.$branch->hospital_id.'-2026-000001']);
            $this->assertDatabaseHas('patient_number_sequences', ['hospital_id' => $branch->hospital_id, 'year' => 2026, 'last_number' => 1]);
        }
    }

    public function test_demo_patient_seeding_refuses_production_environments(): void
    {
        $environment = $this->app->environment();
        $this->app->instance('env', 'production');

        try {
            $this->app->make(PatientSeeder::class)->run();
            $this->fail('Fictional patients must not be seeded in production.');
        } catch (LogicException $exception) {
            $this->assertSame('Fictional patients are only available in local/testing environments.', $exception->getMessage());
        } finally {
            $this->app->instance('env', $environment);
        }

        $this->assertDatabaseCount('patients', 0);
        $this->assertDatabaseCount('patient_number_sequences', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function register(Branch $branch, array $overrides = []): Patient
    {
        return $this->service()->create($branch->hospital, $branch, [...$this->demographics(), ...$overrides]);
    }

    private function service(): PatientIdentityService
    {
        return app(PatientIdentityService::class);
    }

    /**
     * @return array{first_name: string, date_of_birth: string, gender: string, mobile: string}
     */
    private function demographics(): array
    {
        return ['first_name' => 'Synthetic patient', 'date_of_birth' => '1990-05-20', 'gender' => 'female', 'mobile' => '9876543210'];
    }

    private function assertValidationFailure(callable $operation, string $field): void
    {
        try {
            $operation();
            $this->fail('Expected validation to reject '.$field.'.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
