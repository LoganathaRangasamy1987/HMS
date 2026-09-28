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
use Illuminate\Support\Str;
use Tests\TestCase;

class LaboratoryAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_paid_order_becomes_verified_report_and_retains_corrected_revision(): void
    {
        [$order, $parameter, $technician] = $this->orderedTest();
        $invoice = $order->invoice;
        $this->assertSame('ISSUED', $invoice->status);
        $this->assertSame('850.00', $invoice->total);
        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", [
            'amount' => '850.00', 'mode' => 'CASH', 'request_key' => (string) Str::uuid(),
        ])->assertCreated()->assertJsonPath('invoice.status', 'PAID');

        $item = $order->items->firstOrFail();
        $specimenId = $this->postJson("/api/v1/laboratory/orders/{$order->id}/items/{$item->id}/specimens")->assertCreated()->json('data.id');
        $this->putJson("/api/v1/laboratory/orders/{$order->id}/specimens/{$specimenId}/receive")->assertOk();
        $this->putJson("/api/v1/laboratory/orders/{$order->id}/specimens/{$specimenId}/process")->assertOk();

        $this->signInAs($technician);
        $firstId = $this->postJson("/api/v1/laboratory/specimens/{$specimenId}/results")->assertCreated()->json('data.id');
        $this->putJson("/api/v1/laboratory/results/{$firstId}", ['values' => [['parameter_id' => $parameter->id, 'value' => '18.5']]])
            ->assertOk()->assertJsonPath('data.values.0.flag', 'HIGH');
        $this->get("/laboratory/results/{$firstId}/report")->assertStatus(409);

        $this->signIn('doctor@lotus.test');
        $this->putJson("/api/v1/laboratory/results/{$firstId}/finalize")->assertOk()->assertJsonPath('data.order.status', 'VERIFIED');
        $this->get("/laboratory/results/{$firstId}/report")->assertOk()->assertSee('18.5000')->assertSee('HIGH');

        $this->signInAs($technician);
        $secondId = $this->postJson("/api/v1/laboratory/results/{$firstId}/corrections", ['reason' => 'Analyzer calibration adjustment'])
            ->assertCreated()->assertJsonPath('data.revision', 2)->json('data.id');
        $this->get("/laboratory/results/{$secondId}/report")->assertStatus(409);
        $this->putJson("/api/v1/laboratory/results/{$secondId}", ['values' => [['parameter_id' => $parameter->id, 'value' => '15.0']]])
            ->assertOk()->assertJsonPath('data.values.0.flag', 'NORMAL');

        $this->signIn('doctor@lotus.test');
        $this->putJson("/api/v1/laboratory/results/{$secondId}/finalize")->assertOk();
        $this->get("/laboratory/results/{$secondId}/report")->assertOk()->assertSee('15.0000')->assertSee('Analyzer calibration adjustment')->assertSeeText('Report revision: 2');
        $this->assertDatabaseHas('lab_result_values', ['lab_result_id' => $firstId, 'numeric_value' => '18.5', 'flag' => 'HIGH']);
        $this->assertDatabaseHas('lab_result_values', ['lab_result_id' => $secondId, 'numeric_value' => '15', 'flag' => 'NORMAL']);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'PAID', 'total' => '850']);
        $this->assertSame(2, LabResult::where('lab_specimen_id', $specimenId)->count());
    }

    public function test_result_roles_and_patient_hospital_boundaries_hold_across_the_journey(): void
    {
        [$order, $parameter, $technician] = $this->orderedTest();
        $item = $order->items->firstOrFail();
        $foreignPatient = Patient::where('hospital_id', '!=', $order->hospital_id)->firstOrFail();
        $this->postJson('/api/v1/laboratory/orders', [
            'patient_id' => $foreignPatient->id, 'doctor_profile_id' => $order->doctor_profile_id, 'test_ids' => [$item->lab_test_id],
        ])->assertNotFound();

        $specimenId = $this->postJson("/api/v1/laboratory/orders/{$order->id}/items/{$item->id}/specimens")->json('data.id');
        $this->putJson("/api/v1/laboratory/orders/{$order->id}/specimens/{$specimenId}/receive")->assertOk();
        $this->putJson("/api/v1/laboratory/orders/{$order->id}/specimens/{$specimenId}/process")->assertOk();
        $this->signIn('doctor@lotus.test');
        $this->postJson("/api/v1/laboratory/specimens/{$specimenId}/results")->assertForbidden();

        $this->signInAs($technician);
        $resultId = $this->postJson("/api/v1/laboratory/specimens/{$specimenId}/results")->assertCreated()->json('data.id');
        $this->putJson("/api/v1/laboratory/results/{$resultId}", ['values' => [['parameter_id' => $parameter->id, 'value' => '14']]])->assertOk();
        $this->putJson("/api/v1/laboratory/results/{$resultId}/finalize")->assertForbidden();
        $this->signIn('reception@lotus.test');
        $this->getJson("/api/v1/laboratory/results/{$resultId}")->assertForbidden();
        $this->get("/laboratory/results/{$resultId}/report")->assertForbidden();
        $this->signIn('admin@river.test', 'SLM');
        $this->getJson("/api/v1/laboratory/results/{$resultId}")->assertNotFound();
        $this->getJson("/api/v1/laboratory/orders/{$order->id}")->assertNotFound();

        $this->signIn('doctor@lotus.test');
        $this->putJson("/api/v1/laboratory/results/{$resultId}/finalize")->assertOk();
        $this->getJson("/api/v1/laboratory/results/{$resultId}")->assertOk()->assertJsonPath('data.order.patient.id', $order->patient_id);
        $this->get("/laboratory/results/{$resultId}/report")->assertOk();

        $otherDoctor = $this->otherDoctor($order->branch_id, $order->hospital_id);
        $this->signIn('reception@lotus.test');
        $otherOrderId = $this->postJson('/api/v1/laboratory/orders', [
            'patient_id' => $order->patient_id, 'doctor_profile_id' => $otherDoctor->id, 'test_ids' => [$item->lab_test_id],
        ])->assertCreated()->json('data.id');
        $otherOrder = LabOrder::with('items')->findOrFail($otherOrderId);
        $otherItem = $otherOrder->items->firstOrFail();
        $otherSpecimenId = $this->postJson("/api/v1/laboratory/orders/{$otherOrder->id}/items/{$otherItem->id}/specimens")->json('data.id');
        $this->putJson("/api/v1/laboratory/orders/{$otherOrder->id}/specimens/{$otherSpecimenId}/receive")->assertOk();
        $this->putJson("/api/v1/laboratory/orders/{$otherOrder->id}/specimens/{$otherSpecimenId}/process")->assertOk();
        $this->signInAs($technician);
        $otherResultId = $this->postJson("/api/v1/laboratory/specimens/{$otherSpecimenId}/results")->assertCreated()->json('data.id');
        $this->putJson("/api/v1/laboratory/results/{$otherResultId}", ['values' => [['parameter_id' => $parameter->id, 'value' => '13']]])->assertOk();
        $this->signIn('doctor@lotus.test');
        $this->getJson("/api/v1/laboratory/results/{$otherResultId}")->assertNotFound();
        $this->getJson("/api/v1/laboratory/orders/{$otherOrderId}")->assertNotFound();
        $this->assertDatabaseCount('lab_orders', 2);
    }

    /** @return array{LabOrder, LabTestParameter, User} */
    private function orderedTest(): array
    {
        $branch = Branch::where('code', 'CBE')->firstOrFail();
        $doctorUser = User::where('email', 'doctor@lotus.test')->firstOrFail();
        $doctor = DoctorProfile::factory()->create([
            'hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'user_id' => $doctorUser->id,
            'department_id' => Department::where('branch_id', $branch->id)->firstOrFail()->id,
        ]);
        $technician = $this->technician($branch);
        $category = LabCategory::factory()->create(['hospital_id' => $branch->hospital_id]);
        $sample = LabSampleType::factory()->create(['hospital_id' => $branch->hospital_id]);
        $unit = LabUnit::factory()->create(['hospital_id' => $branch->hospital_id, 'symbol' => 'g/dL']);
        $test = LabTest::factory()->create(['hospital_id' => $branch->hospital_id, 'code' => 'CBC-ACC', 'name' => 'Acceptance CBC']);
        $version = LabTestVersion::factory()->create([
            'lab_test_id' => $test->id, 'category_id' => $category->id, 'sample_type_id' => $sample->id, 'status' => 'active',
            'price' => '850.00', 'created_by' => $doctorUser->id, 'activated_at' => now(),
        ]);
        $parameter = LabTestParameter::factory()->create([
            'lab_test_version_id' => $version->id, 'code' => 'HB-ACC', 'name' => 'Haemoglobin', 'result_type' => 'NUMERIC',
            'unit_id' => $unit->id, 'reference_min' => 12, 'reference_max' => 16,
        ]);
        $test->update(['active_version_id' => $version->id]);
        $this->signIn('reception@lotus.test');
        $patient = Patient::where('hospital_id', $branch->hospital_id)->firstOrFail();
        $orderId = $this->postJson('/api/v1/laboratory/orders', [
            'patient_id' => $patient->id, 'doctor_profile_id' => $doctor->id, 'test_ids' => [$test->id],
        ])->assertCreated()->json('data.id');

        return [LabOrder::with(['items', 'invoice'])->findOrFail($orderId), $parameter, $technician];
    }

    private function technician(Branch $branch): User
    {
        $technician = User::factory()->create(['hospital_id' => $branch->hospital_id, 'email' => 'acceptance.lab@lotus.test']);
        Membership::create([
            'hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'user_id' => $technician->id,
            'role_id' => Role::where('name', 'LAB_TECHNICIAN')->firstOrFail()->id, 'status' => 'active',
        ]);

        return $technician;
    }

    private function otherDoctor(int $branchId, int $hospitalId): DoctorProfile
    {
        $user = User::factory()->create(['hospital_id' => $hospitalId, 'email' => 'other.doctor@lotus.test']);
        Membership::create([
            'hospital_id' => $hospitalId, 'branch_id' => $branchId, 'user_id' => $user->id,
            'role_id' => Role::where('name', 'DOCTOR')->firstOrFail()->id, 'status' => 'active',
        ]);

        return DoctorProfile::factory()->create([
            'hospital_id' => $hospitalId, 'branch_id' => $branchId, 'user_id' => $user->id,
            'department_id' => Department::where('branch_id', $branchId)->firstOrFail()->id,
        ]);
    }

    private function signInAs(User $user, string $branchCode = 'CBE'): void
    {
        $membership = $user->memberships()->whereHas('branch', fn ($query) => $query->where('code', $branchCode))->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);
    }

    private function signIn(string $email, string $branchCode = 'CBE'): void
    {
        $this->signInAs(User::where('email', $email)->firstOrFail(), $branchCode);
    }
}
