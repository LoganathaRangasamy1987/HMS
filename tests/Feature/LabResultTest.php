<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Department;
use App\Models\DoctorProfile;
use App\Models\LabCategory;
use App\Models\LabOrder;
use App\Models\LabResult;
use App\Models\LabSampleType;
use App\Models\LabTest;
use App\Models\LabTestParameter;
use App\Models\LabTestVersion;
use App\Models\LabUnit;
use App\Models\Membership;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class LabResultTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_technician_enters_typed_values_with_snapshots_and_reference_flags(): void
    {
        [$order, $specimen, $parameters, $technician] = $this->processingSpecimen();
        $this->signInAs($technician);
        $resultId = $this->postJson("/api/v1/laboratory/specimens/{$specimen}/results")->assertCreated()->assertJsonPath('data.status', 'DRAFT')->json('data.id');
        $this->putJson("/api/v1/laboratory/results/{$resultId}", ['values' => $this->values($parameters, '18.5')])
            ->assertOk()->assertJsonPath('data.values.0.numeric_value', '18.5000')->assertJsonPath('data.values.0.flag', 'HIGH')
            ->assertJsonPath('data.values.1.text_value', 'Clear')->assertJsonPath('data.values.2.boolean_value', true);

        $this->assertDatabaseHas('lab_result_values', ['lab_result_id' => $resultId, 'parameter_code' => 'HB', 'unit_symbol' => 'g/dL', 'reference_min' => '12', 'reference_max' => '16', 'flag' => 'HIGH']);
        $this->assertDatabaseHas('audit_logs', ['module' => 'lab_results', 'action' => 'values_saved', 'record_id' => $resultId]);
        $this->get("/laboratory/results/{$resultId}")->assertOk()->assertSee('Haemoglobin')->assertSee('18.5000');
        $this->assertSame('PROCESSING', $order->fresh()->status);
    }

    public function test_result_validation_requires_exact_typed_parameter_set_and_draft_owner(): void
    {
        [, $specimen, $parameters, $technician] = $this->processingSpecimen();
        $this->signInAs($technician);
        $resultId = $this->postJson("/api/v1/laboratory/specimens/{$specimen}/results")->json('data.id');
        $this->putJson("/api/v1/laboratory/results/{$resultId}", ['values' => [$this->values($parameters)[0]]])->assertUnprocessable()->assertJsonValidationErrors('values');
        $invalid = $this->values($parameters);
        $invalid[0]['value'] = 'not numeric';
        $invalid[1]['flag'] = 'UNKNOWN';
        $this->putJson("/api/v1/laboratory/results/{$resultId}", ['values' => $invalid])->assertUnprocessable()->assertJsonValidationErrors('values.0.value');
        $otherTechnician = $this->technician('other.lab@lotus.test');
        $this->signInAs($otherTechnician);
        $this->putJson("/api/v1/laboratory/results/{$resultId}", ['values' => $this->values($parameters)])->assertUnprocessable()->assertJsonValidationErrors('result');
        $this->assertDatabaseCount('lab_result_values', 0);
    }

    public function test_independent_doctor_verification_finalizes_and_locks_report(): void
    {
        [$order, $specimen, $parameters, $technician] = $this->processingSpecimen();
        $this->signInAs($technician);
        $resultId = $this->postJson("/api/v1/laboratory/specimens/{$specimen}/results")->json('data.id');
        $this->putJson("/api/v1/laboratory/results/{$resultId}", ['values' => $this->values($parameters)])->assertOk();
        $this->putJson("/api/v1/laboratory/results/{$resultId}/finalize")->assertForbidden();
        $this->signIn('doctor@lotus.test');
        $this->putJson("/api/v1/laboratory/results/{$resultId}/finalize")->assertOk()->assertJsonPath('data.status', 'FINAL')->assertJsonPath('data.order.status', 'VERIFIED');
        $this->get("/laboratory/results/{$resultId}/report")->assertOk()->assertSee('Laboratory report')->assertSee($order->number)->assertSee('NORMAL');
        $value = LabResult::findOrFail($resultId)->values()->firstOrFail();
        $this->expectException(LogicException::class);
        $value->update(['numeric_value' => '99']);
    }

    public function test_same_person_cannot_enter_and_verify_a_revision(): void
    {
        [, $specimen, $parameters] = $this->processingSpecimen();
        $this->signIn('admin@lotus.test');
        $resultId = $this->postJson("/api/v1/laboratory/specimens/{$specimen}/results")->assertCreated()->json('data.id');
        $this->putJson("/api/v1/laboratory/results/{$resultId}", ['values' => $this->values($parameters)])->assertOk();
        $this->putJson("/api/v1/laboratory/results/{$resultId}/finalize")->assertUnprocessable()->assertJsonValidationErrors('result');
        $this->assertDatabaseHas('lab_results', ['id' => $resultId, 'status' => 'DRAFT', 'verified_by' => null]);
    }

    public function test_reasoned_correction_creates_new_revision_and_preserves_final_history(): void
    {
        [, $specimen, $parameters, $technician] = $this->processingSpecimen();
        $first = $this->finalizedResult($specimen, $parameters, $technician);
        $this->signInAs($technician);
        $secondId = $this->postJson("/api/v1/laboratory/results/{$first->id}/corrections", ['reason' => 'Analyzer calibration correction'])
            ->assertCreated()->assertJsonPath('data.revision', 2)->assertJsonPath('data.supersedes_lab_result_id', $first->id)->json('data.id');
        $this->putJson("/api/v1/laboratory/results/{$secondId}", ['values' => $this->values($parameters, '11.0')])->assertOk()->assertJsonPath('data.values.0.flag', 'LOW');
        $this->signIn('doctor@lotus.test');
        $this->putJson("/api/v1/laboratory/results/{$secondId}/finalize")->assertOk()->assertJsonPath('data.status', 'FINAL');
        $this->assertDatabaseHas('lab_result_values', ['lab_result_id' => $first->id, 'numeric_value' => '14', 'flag' => 'NORMAL']);
        $this->assertDatabaseHas('lab_result_values', ['lab_result_id' => $secondId, 'numeric_value' => '11', 'flag' => 'LOW']);
        $this->get("/laboratory/results/{$secondId}/report")->assertOk()->assertSee('Analyzer calibration correction')->assertSeeText('Report revision: 2');
        $this->signInAs($technician);
        $this->postJson("/api/v1/laboratory/results/{$first->id}/corrections", ['reason' => 'Invalid older revision'])->assertUnprocessable()->assertJsonValidationErrors('result');
    }

    public function test_result_permissions_and_hospital_isolation_are_enforced(): void
    {
        [, $specimen, $parameters, $technician] = $this->processingSpecimen();
        $this->signInAs($technician);
        $resultId = $this->postJson("/api/v1/laboratory/specimens/{$specimen}/results")->json('data.id');
        $this->putJson("/api/v1/laboratory/results/{$resultId}", ['values' => $this->values($parameters)])->assertOk();
        $this->signIn('reception@lotus.test');
        $this->getJson("/api/v1/laboratory/results/{$resultId}")->assertForbidden();
        $this->signIn('admin@river.test', 'SLM');
        $this->getJson("/api/v1/laboratory/results/{$resultId}")->assertNotFound();
        $this->putJson("/api/v1/laboratory/results/{$resultId}/finalize")->assertNotFound();
    }

    /** @return array{LabOrder, int, array<int, LabTestParameter>, User} */
    private function processingSpecimen(): array
    {
        $branch = Branch::where('code', 'CBE')->firstOrFail();
        $patient = Patient::where('hospital_id', $branch->hospital_id)->firstOrFail();
        $doctorUser = User::where('email', 'doctor@lotus.test')->firstOrFail();
        $doctor = DoctorProfile::factory()->create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'user_id' => $doctorUser->id, 'department_id' => Department::where('branch_id', $branch->id)->firstOrFail()->id]);
        $category = LabCategory::factory()->create(['hospital_id' => $branch->hospital_id]);
        $sample = LabSampleType::factory()->create(['hospital_id' => $branch->hospital_id]);
        $unit = LabUnit::factory()->create(['hospital_id' => $branch->hospital_id, 'symbol' => 'g/dL']);
        $test = LabTest::factory()->create(['hospital_id' => $branch->hospital_id, 'code' => 'CBC', 'name' => 'Complete blood count']);
        $version = LabTestVersion::factory()->create(['lab_test_id' => $test->id, 'category_id' => $category->id, 'sample_type_id' => $sample->id, 'status' => 'active', 'created_by' => $doctorUser->id, 'activated_at' => now()]);
        $parameters = [
            LabTestParameter::factory()->create(['lab_test_version_id' => $version->id, 'code' => 'HB', 'name' => 'Haemoglobin', 'result_type' => 'NUMERIC', 'unit_id' => $unit->id, 'reference_min' => '12', 'reference_max' => '16', 'sort_order' => 1]),
            LabTestParameter::factory()->create(['lab_test_version_id' => $version->id, 'code' => 'APPEAR', 'name' => 'Appearance', 'result_type' => 'TEXT', 'unit_id' => null, 'reference_min' => null, 'reference_max' => null, 'reference_text' => 'Clear', 'sort_order' => 2]),
            LabTestParameter::factory()->create(['lab_test_version_id' => $version->id, 'code' => 'REACTIVE', 'name' => 'Reactive', 'result_type' => 'BOOLEAN', 'unit_id' => null, 'reference_min' => null, 'reference_max' => null, 'reference_text' => 'Negative', 'sort_order' => 3]),
        ];
        $test->update(['active_version_id' => $version->id]);
        $this->signIn('reception@lotus.test');
        $orderId = $this->postJson('/api/v1/laboratory/orders', ['patient_id' => $patient->id, 'doctor_profile_id' => $doctor->id, 'test_ids' => [$test->id]])->json('data.id');
        $order = LabOrder::with('items')->findOrFail($orderId);
        $item = $order->items->first();
        $specimen = $this->postJson("/api/v1/laboratory/orders/{$order->id}/items/{$item->id}/specimens")->json('data.id');
        $this->putJson("/api/v1/laboratory/orders/{$order->id}/specimens/{$specimen}/receive")->assertOk();
        $this->putJson("/api/v1/laboratory/orders/{$order->id}/specimens/{$specimen}/process")->assertOk();

        return [$order, $specimen, $parameters, $this->technician()];
    }

    /** @param array<int, LabTestParameter> $parameters
     * @return array<int, array<string, mixed>>
     */
    private function values(array $parameters, string $numeric = '14.0'): array
    {
        return [
            ['parameter_id' => $parameters[0]->id, 'value' => $numeric],
            ['parameter_id' => $parameters[1]->id, 'value' => 'Clear', 'flag' => 'NORMAL'],
            ['parameter_id' => $parameters[2]->id, 'value' => true, 'flag' => 'ABNORMAL'],
        ];
    }

    /** @param array<int, LabTestParameter> $parameters */
    private function finalizedResult(int $specimen, array $parameters, User $technician): LabResult
    {
        $this->signInAs($technician);
        $resultId = $this->postJson("/api/v1/laboratory/specimens/{$specimen}/results")->json('data.id');
        $this->putJson("/api/v1/laboratory/results/{$resultId}", ['values' => $this->values($parameters)])->assertOk();
        $this->signIn('doctor@lotus.test');
        $this->putJson("/api/v1/laboratory/results/{$resultId}/finalize")->assertOk();

        return LabResult::findOrFail($resultId);
    }

    private function technician(string $email = 'lab.tech@lotus.test'): User
    {
        $existing = User::where('email', $email)->first();
        if ($existing) {
            return $existing;
        }
        $branch = Branch::where('code', 'CBE')->firstOrFail();
        $technician = User::factory()->create(['hospital_id' => $branch->hospital_id, 'email' => $email]);
        Membership::create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'user_id' => $technician->id, 'role_id' => Role::where('name', 'LAB_TECHNICIAN')->firstOrFail()->id, 'status' => 'active']);

        return $technician;
    }

    private function signInAs(User $user): void
    {
        $membership = $user->memberships()->whereHas('branch', fn ($query) => $query->where('code', 'CBE'))->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);
    }

    private function signIn(string $email, string $branchCode = 'CBE'): void
    {
        $user = User::where('email', $email)->firstOrFail();
        $membership = $user->memberships()->whereHas('branch', fn ($query) => $query->where('code', $branchCode))->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);
    }
}
