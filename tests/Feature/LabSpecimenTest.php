<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Department;
use App\Models\DoctorProfile;
use App\Models\LabCategory;
use App\Models\LabOrder;
use App\Models\LabSampleType;
use App\Models\LabSpecimen;
use App\Models\LabTest;
use App\Models\LabTestVersion;
use App\Models\Membership;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class LabSpecimenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_reception_collects_uniquely_labelled_and_attributed_specimen(): void
    {
        $order = $this->createOrder();
        $item = $order->items->first();
        $specimenId = $this->postJson("/api/v1/laboratory/orders/{$order->id}/items/{$item->id}/specimens")
            ->assertCreated()->assertJsonPath('data.status', 'COLLECTED')->assertJsonPath('data.attempt', 1)
            ->assertJsonPath('data.events.0.to_status', 'COLLECTED')->json('data.id');
        $specimen = LabSpecimen::findOrFail($specimenId);
        $this->assertMatchesRegularExpression('/^SPC-\d+-\d{4}-\d{7}$/', $specimen->identifier);
        $this->assertSame(auth()->id(), $specimen->collected_by);
        $this->assertDatabaseHas('lab_order_items', ['id' => $item->id, 'status' => 'COLLECTED']);
        $this->assertDatabaseHas('lab_orders', ['id' => $order->id, 'status' => 'COLLECTED']);
        $this->get("/laboratory/orders/{$order->id}/specimens/{$specimen->id}/label")->assertOk()->assertSee($specimen->identifier)->assertSee($item->test_name);
    }

    public function test_specimen_follows_collection_receipt_and_processing_sequence_with_immutable_history(): void
    {
        $order = $this->createOrder();
        $item = $order->items->first();
        $specimenId = $this->postJson("/api/v1/laboratory/orders/{$order->id}/items/{$item->id}/specimens")->json('data.id');
        $this->putJson("/api/v1/laboratory/orders/{$order->id}/specimens/{$specimenId}/receive")->assertOk()->assertJsonPath('data.status', 'RECEIVED')->assertJsonPath('data.events.1.to_status', 'RECEIVED');
        $this->putJson("/api/v1/laboratory/orders/{$order->id}/specimens/{$specimenId}/process")->assertOk()->assertJsonPath('data.status', 'PROCESSING')->assertJsonPath('data.events.2.to_status', 'PROCESSING');
        $specimen = LabSpecimen::with('events')->findOrFail($specimenId);
        $this->assertNotNull($specimen->received_at);
        $this->assertNotNull($specimen->processing_at);
        $this->assertDatabaseHas('lab_orders', ['id' => $order->id, 'status' => 'PROCESSING']);
        $this->expectException(LogicException::class);
        $specimen->events->first()->update(['to_status' => 'REJECTED']);
    }

    public function test_invalid_or_duplicate_transitions_are_rejected(): void
    {
        $order = $this->createOrder();
        $item = $order->items->first();
        $specimenId = $this->postJson("/api/v1/laboratory/orders/{$order->id}/items/{$item->id}/specimens")->json('data.id');
        $this->postJson("/api/v1/laboratory/orders/{$order->id}/items/{$item->id}/specimens")->assertUnprocessable()->assertJsonValidationErrors('specimen');
        $this->putJson("/api/v1/laboratory/orders/{$order->id}/specimens/{$specimenId}/process")->assertUnprocessable()->assertJsonValidationErrors('specimen');
        $this->putJson("/api/v1/laboratory/orders/{$order->id}/specimens/{$specimenId}/reject", ['reason' => 'bad'])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->assertDatabaseCount('lab_specimens', 1);
        $this->assertDatabaseCount('lab_specimen_events', 1);
    }

    public function test_rejection_requires_recollection_and_preserves_attempt_history(): void
    {
        $order = $this->createOrder();
        $item = $order->items->first();
        $first = $this->postJson("/api/v1/laboratory/orders/{$order->id}/items/{$item->id}/specimens")->json('data');
        $this->putJson("/api/v1/laboratory/orders/{$order->id}/specimens/{$first['id']}/reject", ['reason' => 'Insufficient sample volume'])->assertOk()->assertJsonPath('data.status', 'REJECTED')->assertJsonPath('data.rejection_reason', 'Insufficient sample volume');
        $this->assertDatabaseHas('lab_orders', ['id' => $order->id, 'status' => 'RECOLLECTION_REQUIRED']);
        $second = $this->postJson("/api/v1/laboratory/orders/{$order->id}/items/{$item->id}/specimens")->assertCreated()->assertJsonPath('data.attempt', 2)->json('data');
        $this->assertNotSame($first['identifier'], $second['identifier']);
        $this->assertDatabaseHas('lab_specimens', ['id' => $first['id'], 'status' => 'REJECTED', 'attempt' => 1]);
        $this->assertDatabaseHas('lab_specimens', ['id' => $second['id'], 'status' => 'COLLECTED', 'attempt' => 2]);
        $this->putJson("/api/v1/laboratory/orders/{$order->id}/specimens/{$first['id']}/receive")->assertUnprocessable()->assertJsonValidationErrors('specimen');
    }

    public function test_specimen_activity_blocks_order_cancellation(): void
    {
        $order = $this->createOrder();
        $item = $order->items->first();
        $this->postJson("/api/v1/laboratory/orders/{$order->id}/items/{$item->id}/specimens")->assertCreated();
        $this->putJson("/api/v1/laboratory/orders/{$order->id}/cancel", ['reason' => 'No longer required'])->assertUnprocessable()->assertJsonValidationErrors('order');
        $this->assertDatabaseHas('lab_orders', ['id' => $order->id, 'status' => 'COLLECTED']);
    }

    public function test_multi_test_order_tracks_partial_and_complete_collection(): void
    {
        $order = $this->createOrder(2);
        $this->postJson("/api/v1/laboratory/orders/{$order->id}/items/{$order->items[0]->id}/specimens")->assertCreated();
        $this->assertDatabaseHas('lab_orders', ['id' => $order->id, 'status' => 'PARTIALLY_COLLECTED']);
        $this->postJson("/api/v1/laboratory/orders/{$order->id}/items/{$order->items[1]->id}/specimens")->assertCreated();
        $this->assertDatabaseHas('lab_orders', ['id' => $order->id, 'status' => 'COLLECTED']);
    }

    public function test_permissions_and_hospital_isolation_apply_to_specimens(): void
    {
        $order = $this->createOrder();
        $item = $order->items->first();
        $specimenId = $this->postJson("/api/v1/laboratory/orders/{$order->id}/items/{$item->id}/specimens")->json('data.id');
        $this->signIn('doctor@lotus.test');
        $this->get("/laboratory/orders/{$order->id}/specimens/{$specimenId}/label")->assertOk();
        $this->putJson("/api/v1/laboratory/orders/{$order->id}/specimens/{$specimenId}/receive")->assertForbidden();
        $branch = Branch::where('code', 'CBE')->firstOrFail();
        $technician = User::factory()->create(['hospital_id' => $branch->hospital_id, 'email' => 'lab.tech@lotus.test']);
        $membership = Membership::create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'user_id' => $technician->id, 'role_id' => Role::where('name', 'LAB_TECHNICIAN')->firstOrFail()->id, 'status' => 'active']);
        $this->actingAs($technician)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $technician->getAuthPassword()]);
        $this->putJson("/api/v1/laboratory/orders/{$order->id}/specimens/{$specimenId}/receive")->assertOk()->assertJsonPath('data.status', 'RECEIVED');
        $this->signIn('admin@river.test', 'SLM');
        $this->get("/laboratory/orders/{$order->id}/specimens/{$specimenId}/label")->assertNotFound();
        $this->putJson("/api/v1/laboratory/orders/{$order->id}/specimens/{$specimenId}/receive")->assertNotFound();
    }

    private function createOrder(int $testCount = 1): LabOrder
    {
        $branch = Branch::where('code', 'CBE')->firstOrFail();
        $patient = Patient::where('hospital_id', $branch->hospital_id)->firstOrFail();
        $doctorUser = User::where('email', 'doctor@lotus.test')->firstOrFail();
        $doctor = DoctorProfile::factory()->create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'user_id' => $doctorUser->id, 'department_id' => Department::where('branch_id', $branch->id)->firstOrFail()->id]);
        $testIds = [];
        foreach (range(1, $testCount) as $sequence) {
            $category = LabCategory::factory()->create(['hospital_id' => $branch->hospital_id]);
            $sample = LabSampleType::factory()->create(['hospital_id' => $branch->hospital_id]);
            $test = LabTest::factory()->create(['hospital_id' => $branch->hospital_id, 'code' => "TEST-{$sequence}"]);
            $version = LabTestVersion::factory()->create(['lab_test_id' => $test->id, 'category_id' => $category->id, 'sample_type_id' => $sample->id, 'status' => 'active', 'created_by' => $doctorUser->id, 'activated_at' => now()]);
            $test->update(['active_version_id' => $version->id]);
            $testIds[] = $test->id;
        }
        $this->signIn('reception@lotus.test');
        $orderId = $this->postJson('/api/v1/laboratory/orders', ['patient_id' => $patient->id, 'doctor_profile_id' => $doctor->id, 'test_ids' => $testIds])->assertCreated()->json('data.id');

        return LabOrder::with('items')->findOrFail($orderId);
    }

    private function signIn(string $email, string $branchCode = 'CBE'): void
    {
        $user = User::where('email', $email)->firstOrFail();
        $membership = $user->memberships()->whereHas('branch', fn ($query) => $query->where('code', $branchCode))->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);
    }
}
