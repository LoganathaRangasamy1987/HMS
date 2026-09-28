<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Department;
use App\Models\DoctorProfile;
use App\Models\LabCategory;
use App\Models\LabNotification;
use App\Models\LabOrder;
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
use Tests\TestCase;

class LabWorklistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_worklists_route_each_stage_to_authorized_roles(): void
    {
        [$order, $parameter, $technician] = $this->orderedTest();
        $this->signIn('reception@lotus.test');
        $this->getJson('/api/v1/laboratory/worklist')->assertOk()->assertJsonCount(1, 'data.collection')->assertJsonCount(0, 'data.verification')->assertJsonCount(0, 'data.reports');
        $item = $order->items->firstOrFail();
        $specimenId = $this->postJson("/api/v1/laboratory/orders/{$order->id}/items/{$item->id}/specimens")->assertCreated()->json('data.id');
        $this->putJson("/api/v1/laboratory/orders/{$order->id}/specimens/{$specimenId}/receive")->assertOk();
        $this->putJson("/api/v1/laboratory/orders/{$order->id}/specimens/{$specimenId}/process")->assertOk();

        $this->signInAs($technician);
        $resultId = $this->postJson("/api/v1/laboratory/specimens/{$specimenId}/results")->assertCreated()->json('data.id');
        $this->putJson("/api/v1/laboratory/results/{$resultId}", ['values' => [['parameter_id' => $parameter->id, 'value' => '14.0']]])->assertOk();
        $this->getJson('/api/v1/laboratory/worklist')->assertOk()->assertJsonCount(0, 'data.verification')->assertJsonCount(0, 'data.reports');

        $this->signIn('doctor@lotus.test');
        $this->getJson('/api/v1/laboratory/worklist')->assertOk()->assertJsonCount(0, 'data.collection')->assertJsonCount(1, 'data.verification');
        $this->putJson("/api/v1/laboratory/results/{$resultId}/finalize")->assertOk();
        $this->getJson('/api/v1/laboratory/worklist')->assertOk()->assertJsonCount(0, 'data.verification')->assertJsonCount(1, 'data.reports');

        $this->signIn('reception@lotus.test');
        $this->getJson('/api/v1/laboratory/worklist')->assertOk()->assertJsonCount(0, 'data.reports');
        $this->get("/laboratory/results/{$resultId}/report")->assertForbidden();
    }

    public function test_lifecycle_notifications_are_deduplicated_and_recipient_scoped(): void
    {
        [$order, $parameter, $technician] = $this->orderedTest();
        $notification = LabNotification::where('recipient_user_id', $technician->id)->where('type', 'ORDER_CREATED')->firstOrFail();
        $this->signInAs($technician);
        $this->putJson("/api/v1/laboratory/notifications/{$notification->id}/read")->assertOk()->assertJsonPath('data.read_at', fn ($value) => $value !== null);
        $this->assertNotNull($notification->fresh()->read_at);
        $this->signIn('doctor@lotus.test');
        $this->putJson("/api/v1/laboratory/notifications/{$notification->id}/read")->assertNotFound();

        $this->signIn('reception@lotus.test');
        $item = $order->items->firstOrFail();
        $specimenId = $this->postJson("/api/v1/laboratory/orders/{$order->id}/items/{$item->id}/specimens")->json('data.id');
        $this->putJson("/api/v1/laboratory/orders/{$order->id}/specimens/{$specimenId}/receive")->assertOk();
        $this->putJson("/api/v1/laboratory/orders/{$order->id}/specimens/{$specimenId}/process")->assertOk();
        $this->signInAs($technician);
        $resultId = $this->postJson("/api/v1/laboratory/specimens/{$specimenId}/results")->json('data.id');
        $values = ['values' => [['parameter_id' => $parameter->id, 'value' => '14.0']]];
        $this->putJson("/api/v1/laboratory/results/{$resultId}", $values)->assertOk();
        $this->putJson("/api/v1/laboratory/results/{$resultId}", $values)->assertOk();
        $doctor = User::where('email', 'doctor@lotus.test')->firstOrFail();
        $this->assertSame(1, LabNotification::where('recipient_user_id', $doctor->id)->where('type', 'RESULT_READY')->count());
        $this->signInAs($doctor);
        $this->putJson("/api/v1/laboratory/results/{$resultId}/finalize")->assertOk();
        $this->assertDatabaseHas('lab_notifications', ['recipient_user_id' => $technician->id, 'type' => 'RESULT_FINALIZED']);
    }

    public function test_rejected_specimen_notifies_doctor_and_returns_item_to_collection(): void
    {
        [$order] = $this->orderedTest();
        $this->signIn('reception@lotus.test');
        $item = $order->items->firstOrFail();
        $specimenId = $this->postJson("/api/v1/laboratory/orders/{$order->id}/items/{$item->id}/specimens")->json('data.id');
        $this->putJson("/api/v1/laboratory/orders/{$order->id}/specimens/{$specimenId}/reject", ['reason' => 'Insufficient sample volume'])->assertOk();
        $doctor = User::where('email', 'doctor@lotus.test')->firstOrFail();
        $this->assertDatabaseHas('lab_notifications', ['recipient_user_id' => $doctor->id, 'type' => 'SPECIMEN_REJECTED']);
        $this->getJson('/api/v1/laboratory/worklist')->assertOk()->assertJsonCount(1, 'data.collection')->assertJsonPath('data.collection.0.status', 'RECOLLECTION_REQUIRED');
        $this->getJson('/api/v1/laboratory/worklist?q=not-present')->assertOk()->assertJsonCount(0, 'data.collection');
    }

    /** @return array{LabOrder, LabTestParameter, User} */
    private function orderedTest(): array
    {
        $branch = Branch::where('code', 'CBE')->firstOrFail();
        $doctorUser = User::where('email', 'doctor@lotus.test')->firstOrFail();
        $doctor = DoctorProfile::factory()->create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'user_id' => $doctorUser->id, 'department_id' => Department::where('branch_id', $branch->id)->firstOrFail()->id]);
        $technician = $this->technician();
        $category = LabCategory::factory()->create(['hospital_id' => $branch->hospital_id]);
        $sample = LabSampleType::factory()->create(['hospital_id' => $branch->hospital_id]);
        $unit = LabUnit::factory()->create(['hospital_id' => $branch->hospital_id]);
        $test = LabTest::factory()->create(['hospital_id' => $branch->hospital_id, 'code' => 'CBC-WL', 'name' => 'Worklist CBC']);
        $version = LabTestVersion::factory()->create(['lab_test_id' => $test->id, 'category_id' => $category->id, 'sample_type_id' => $sample->id, 'status' => 'active', 'created_by' => $doctorUser->id, 'activated_at' => now()]);
        $parameter = LabTestParameter::factory()->create(['lab_test_version_id' => $version->id, 'code' => 'HB-WL', 'result_type' => 'NUMERIC', 'unit_id' => $unit->id, 'reference_min' => 12, 'reference_max' => 16]);
        $test->update(['active_version_id' => $version->id]);
        $this->signIn('reception@lotus.test');
        $patient = Patient::where('hospital_id', $branch->hospital_id)->firstOrFail();
        $orderId = $this->postJson('/api/v1/laboratory/orders', ['patient_id' => $patient->id, 'doctor_profile_id' => $doctor->id, 'test_ids' => [$test->id]])->assertCreated()->json('data.id');

        return [LabOrder::with('items')->findOrFail($orderId), $parameter, $technician];
    }

    private function technician(): User
    {
        $branch = Branch::where('code', 'CBE')->firstOrFail();
        $technician = User::factory()->create(['hospital_id' => $branch->hospital_id, 'email' => 'worklist.lab@lotus.test']);
        Membership::create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'user_id' => $technician->id, 'role_id' => Role::where('name', 'LAB_TECHNICIAN')->firstOrFail()->id, 'status' => 'active']);

        return $technician;
    }

    private function signInAs(User $user): void
    {
        $membership = $user->memberships()->whereHas('branch', fn ($query) => $query->where('code', 'CBE'))->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);
    }

    private function signIn(string $email): void
    {
        $this->signInAs(User::where('email', $email)->firstOrFail());
    }
}
