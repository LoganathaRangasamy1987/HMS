<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Department;
use App\Models\DoctorProfile;
use App\Models\Encounter;
use App\Models\LabCategory;
use App\Models\LabOrder;
use App\Models\LabSampleType;
use App\Models\LabTest;
use App\Models\LabTestVersion;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_reception_creates_order_with_immutable_test_and_invoice_snapshots(): void
    {
        [$patient, $doctor, $test] = $this->fixture();
        $this->signIn('reception@lotus.test');
        $orderId = $this->postJson('/api/v1/laboratory/orders', [
            'patient_id' => $patient->id, 'doctor_profile_id' => $doctor->id, 'test_ids' => [$test->id], 'clinical_notes' => 'Persistent fever.',
        ])->assertCreated()->assertJsonPath('data.status', 'ORDERED')->assertJsonPath('data.items.0.test_code', 'CBC')->assertJsonPath('data.items.0.price', '450.00')->assertJsonPath('data.invoice.status', 'ISSUED')->json('data.id');

        $order = LabOrder::with(['items', 'invoice.lines'])->findOrFail($orderId);
        $this->assertMatchesRegularExpression('/^LAB-\d+-\d{4}-\d{6}$/', $order->number);
        $this->assertSame('450.00', $order->invoice->total);
        $this->assertNull($order->invoice->lines->first()->service_item_id);
        $this->assertSame($order->invoice->lines->first()->id, $order->items->first()->invoice_line_id);
        $this->assertDatabaseHas('audit_logs', ['module' => 'lab_orders', 'action' => 'created', 'record_id' => $order->id]);
        $this->get("/laboratory/orders/{$order->id}")->assertOk()->assertSee($order->number)->assertSee('Persistent fever.');
    }

    public function test_order_snapshot_survives_later_catalog_version_activation(): void
    {
        [$patient, $doctor, $test] = $this->fixture();
        $this->signIn('doctor@lotus.test');
        $orderId = $this->postJson('/api/v1/laboratory/orders', ['patient_id' => $patient->id, 'doctor_profile_id' => $doctor->id, 'test_ids' => [$test->id]])->assertCreated()->json('data.id');
        $oldVersion = $test->activeVersion;
        $oldVersion->update(['status' => 'archived']);
        $newVersion = LabTestVersion::factory()->create(['lab_test_id' => $test->id, 'version' => 2, 'category_id' => $oldVersion->category_id, 'sample_type_id' => $oldVersion->sample_type_id, 'price' => '700.00', 'status' => 'active', 'created_by' => auth()->id(), 'activated_at' => now()]);
        $test->update(['active_version_id' => $newVersion->id]);

        $this->getJson("/api/v1/laboratory/orders/{$orderId}")->assertOk()->assertJsonPath('data.items.0.version', 1)->assertJsonPath('data.items.0.price', '450.00')->assertJsonPath('data.invoice.total', '450.00');
    }

    public function test_doctor_and_encounter_linkage_are_validated(): void
    {
        [$patient, $doctor, $test] = $this->fixture();
        $otherDoctor = DoctorProfile::factory()->create(['hospital_id' => $doctor->hospital_id, 'branch_id' => $doctor->branch_id]);
        $encounter = Encounter::create([
            'hospital_id' => $doctor->hospital_id, 'branch_id' => $doctor->branch_id, 'patient_id' => $patient->id,
            'doctor_profile_id' => $otherDoctor->id, 'department_id' => $otherDoctor->department_id, 'encounter_type' => 'OPD',
            'status' => 'ACTIVE', 'opened_at' => now(), 'opened_by' => $otherDoctor->user_id,
        ]);
        $this->signIn('doctor@lotus.test');
        $this->postJson('/api/v1/laboratory/orders', ['patient_id' => $patient->id, 'doctor_profile_id' => $otherDoctor->id, 'test_ids' => [$test->id]])->assertUnprocessable()->assertJsonValidationErrors('doctor_profile_id');
        $this->postJson('/api/v1/laboratory/orders', ['patient_id' => $patient->id, 'doctor_profile_id' => $doctor->id, 'encounter_id' => $encounter->id, 'test_ids' => [$test->id]])->assertUnprocessable()->assertJsonValidationErrors('encounter_id');
        $this->assertDatabaseCount('lab_orders', 0);
    }

    public function test_unpaid_order_can_be_cancelled_and_invoice_is_voided(): void
    {
        [$patient, $doctor, $test] = $this->fixture();
        $this->signIn('reception@lotus.test');
        $order = $this->postJson('/api/v1/laboratory/orders', ['patient_id' => $patient->id, 'doctor_profile_id' => $doctor->id, 'test_ids' => [$test->id]])->json('data');
        $orderId = $order['id'];
        $this->signIn('admin@lotus.test');
        $this->postJson("/api/v1/invoices/{$order['invoice_id']}/void", ['reason' => 'Wrong direct workflow', 'request_key' => fake()->uuid()])->assertUnprocessable()->assertJsonValidationErrors('invoice');
        $this->signIn('reception@lotus.test');
        $this->putJson("/api/v1/laboratory/orders/{$orderId}/cancel", ['reason' => 'Duplicate request'])->assertOk()->assertJsonPath('data.status', 'CANCELLED')->assertJsonPath('data.invoice.status', 'VOID');
        $this->assertDatabaseHas('lab_order_items', ['lab_order_id' => $orderId, 'status' => 'CANCELLED']);
        $this->putJson("/api/v1/laboratory/orders/{$orderId}/cancel", ['reason' => 'Again duplicate'])->assertUnprocessable()->assertJsonValidationErrors('order');
    }

    public function test_collected_payment_blocks_order_cancellation(): void
    {
        [$patient, $doctor, $test] = $this->fixture();
        $this->signIn('reception@lotus.test');
        $order = $this->postJson('/api/v1/laboratory/orders', ['patient_id' => $patient->id, 'doctor_profile_id' => $doctor->id, 'test_ids' => [$test->id]])->json('data');
        $this->postJson("/api/v1/invoices/{$order['invoice_id']}/payments", ['amount' => '100.00', 'mode' => 'CASH', 'request_key' => fake()->uuid()])->assertCreated();
        $this->putJson("/api/v1/laboratory/orders/{$order['id']}/cancel", ['reason' => 'Patient changed mind'])->assertUnprocessable()->assertJsonValidationErrors('order');
        $this->assertDatabaseHas('lab_orders', ['id' => $order['id'], 'status' => 'ORDERED']);
    }

    public function test_orders_are_branch_and_hospital_scoped(): void
    {
        [$patient, $doctor, $test] = $this->fixture();
        $this->signIn('reception@lotus.test');
        $orderId = $this->postJson('/api/v1/laboratory/orders', ['patient_id' => $patient->id, 'doctor_profile_id' => $doctor->id, 'test_ids' => [$test->id]])->json('data.id');
        $this->signIn('admin@river.test', 'SLM');
        $this->getJson('/api/v1/laboratory/orders')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/laboratory/orders/{$orderId}")->assertNotFound();
        $this->putJson("/api/v1/laboratory/orders/{$orderId}/cancel", ['reason' => 'Foreign attempt'])->assertNotFound();
    }

    /** @return array{Patient, DoctorProfile, LabTest} */
    private function fixture(): array
    {
        $branch = Branch::where('code', 'CBE')->firstOrFail();
        $patient = Patient::where('hospital_id', $branch->hospital_id)->firstOrFail();
        $doctorUser = User::where('email', 'doctor@lotus.test')->firstOrFail();
        $doctor = DoctorProfile::factory()->create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'user_id' => $doctorUser->id, 'department_id' => Department::where('branch_id', $branch->id)->firstOrFail()->id]);
        $category = LabCategory::factory()->create(['hospital_id' => $branch->hospital_id]);
        $sample = LabSampleType::factory()->create(['hospital_id' => $branch->hospital_id]);
        $test = LabTest::factory()->create(['hospital_id' => $branch->hospital_id, 'code' => 'CBC', 'name' => 'Complete blood count']);
        $version = LabTestVersion::factory()->create(['lab_test_id' => $test->id, 'version' => 1, 'category_id' => $category->id, 'sample_type_id' => $sample->id, 'price' => '450.00', 'status' => 'active', 'created_by' => $doctorUser->id, 'activated_at' => now()]);
        $test->update(['active_version_id' => $version->id]);

        return [$patient, $doctor, $test->fresh('activeVersion')];
    }

    private function signIn(string $email, string $branchCode = 'CBE'): void
    {
        $user = User::where('email', $email)->firstOrFail();
        $membership = $user->memberships()->whereHas('branch', fn ($query) => $query->where('code', $branchCode))->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);
    }
}
