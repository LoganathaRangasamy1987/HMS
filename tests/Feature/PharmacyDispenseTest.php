<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\DoctorProfile;
use App\Models\Encounter;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Membership;
use App\Models\Patient;
use App\Models\PharmacySale;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PharmacyDispenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_pharmacist_dispenses_prescription_deducts_stock_and_issues_invoice(): void
    {
        [$user, $prescription, $item, $batch] = $this->fixture();
        $this->signInAs($user);
        $response = $this->postJson('/api/v1/pharmacy/sales', $this->payload($prescription, $item, $batch));

        $response->assertCreated()->assertJsonPath('data.number', 'PHS-1-'.now()->year.'-000001')
            ->assertJsonPath('data.subtotal', '50.00')->assertJsonPath('data.tax_amount', '2.50')->assertJsonPath('data.total', '52.50')
            ->assertJsonPath('data.invoice.status', 'ISSUED')->assertJsonPath('data.invoice.total', '52.50');
        $this->assertSame('8.000', $batch->fresh()->on_hand_quantity);
        $this->assertDatabaseHas('stock_movements', ['medicine_batch_id' => $batch->id, 'type' => 'DISPENSE', 'quantity' => -2, 'balance_after' => 8, 'recorded_by' => $user->id]);
        $this->assertDatabaseHas('invoice_lines', ['service_code' => $item->medicine->code, 'quantity' => 2, 'total' => 52.5]);
        $this->assertDatabaseHas('audit_logs', ['module' => 'pharmacy_sales', 'action' => 'dispensed']);
        $this->get('/pharmacy/sales')->assertOk()->assertSee($prescription->patient->uhid)->assertSee('Completed sales');
    }

    public function test_sale_replay_is_idempotent_and_changed_replay_is_rejected(): void
    {
        [$user, $prescription, $item, $batch] = $this->fixture();
        $this->signInAs($user);
        $payload = $this->payload($prescription, $item, $batch);
        $this->postJson('/api/v1/pharmacy/sales', $payload)->assertCreated();
        $this->postJson('/api/v1/pharmacy/sales', $payload)->assertOk();
        $this->assertSame(1, PharmacySale::count());
        $this->assertSame(1, StockMovement::where('type', 'DISPENSE')->count());
        $payload['items'][0]['quantity'] = 3;
        $this->postJson('/api/v1/pharmacy/sales', $payload)->assertUnprocessable()->assertJsonValidationErrors('request_key');
    }

    public function test_expired_and_insufficient_batches_are_rejected_atomically(): void
    {
        [$user, $prescription, $item, $batch] = $this->fixture();
        $this->signInAs($user);
        $batch->expires_on = now()->subDay()->toDateString();
        $batch->saveQuietly();
        $this->postJson('/api/v1/pharmacy/sales', $this->payload($prescription, $item, $batch))->assertUnprocessable()->assertJsonValidationErrors('items.0.medicine_batch_id');
        $batch->expires_on = now()->addYear()->toDateString();
        $batch->saveQuietly();
        $payload = $this->payload($prescription, $item, $batch, 11);
        $payload['request_key'] = (string) Str::uuid();
        $this->postJson('/api/v1/pharmacy/sales', $payload)->assertUnprocessable()->assertJsonValidationErrors('items.0.quantity');
        $this->assertSame(0, PharmacySale::count());
        $this->assertSame(0, StockMovement::where('type', 'DISPENSE')->count());
    }

    public function test_prescription_line_cannot_be_dispensed_twice(): void
    {
        [$user, $prescription, $item, $batch] = $this->fixture();
        $this->signInAs($user);
        $this->postJson('/api/v1/pharmacy/sales', $this->payload($prescription, $item, $batch))->assertCreated();
        $payload = $this->payload($prescription, $item, $batch);
        $this->postJson('/api/v1/pharmacy/sales', $payload)->assertUnprocessable()->assertJsonValidationErrors('items');
    }

    public function test_permissions_and_branch_scope_are_enforced(): void
    {
        [$user, $prescription, $item, $batch] = $this->fixture();
        $this->signInAs($user);
        $saleId = $this->postJson('/api/v1/pharmacy/sales', $this->payload($prescription, $item, $batch))->assertCreated()->json('data.id');
        $otherBranch = Branch::where('hospital_id', $prescription->hospital_id)->where('id', '!=', $prescription->branch_id)->firstOrFail();
        $membership = Membership::create(['hospital_id' => $otherBranch->hospital_id, 'branch_id' => $otherBranch->id, 'user_id' => $user->id, 'role_id' => Role::where('name', 'PHARMACIST')->firstOrFail()->id, 'status' => 'active']);
        $this->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()])->getJson("/api/v1/pharmacy/sales/{$saleId}")->assertNotFound();
        $this->signIn('reception@lotus.test');
        $this->get('/pharmacy/sales')->assertForbidden();
    }

    /** @return array{User, Prescription, PrescriptionItem, MedicineBatch} */
    private function fixture(): array
    {
        $branch = Branch::where('code', 'CBE')->firstOrFail();
        $patient = Patient::factory()->forBranch($branch)->create();
        $prescriber = User::where('email', 'doctor@lotus.test')->firstOrFail();
        $doctor = DoctorProfile::factory()->create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'user_id' => $prescriber->id]);
        $encounter = Encounter::create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'patient_id' => $patient->id, 'doctor_profile_id' => $doctor->id, 'department_id' => $doctor->department_id, 'encounter_type' => 'OPD', 'status' => 'COMPLETED', 'opened_at' => now()->subHour(), 'opened_by' => $prescriber->id, 'closed_at' => now(), 'closed_by' => $prescriber->id]);
        $prescription = Prescription::create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'patient_id' => $patient->id, 'encounter_id' => $encounter->id, 'prescribed_at' => now(), 'prescribed_by' => $prescriber->id]);
        $medicine = Medicine::factory()->create(['hospital_id' => $branch->hospital_id, 'tax_rate_percent' => '5.00', 'status' => 'active']);
        $item = PrescriptionItem::factory()->create(['prescription_id' => $prescription->id, 'medicine_id' => $medicine->id, 'medicine_name' => $medicine->name]);
        $batch = MedicineBatch::create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'medicine_id' => $medicine->id, 'batch_number' => 'DISP-001', 'manufactured_on' => now()->subMonth(), 'expires_on' => now()->addYear(), 'received_quantity' => '10.000', 'on_hand_quantity' => '10.000', 'unit_cost' => '20.00', 'sale_price' => '25.00', 'status' => 'active']);
        $user = User::factory()->create(['hospital_id' => $branch->hospital_id]);
        Membership::create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'user_id' => $user->id, 'role_id' => Role::where('name', 'PHARMACIST')->firstOrFail()->id, 'status' => 'active']);

        return [$user, $prescription, $item->load('medicine'), $batch];
    }

    private function payload(Prescription $prescription, PrescriptionItem $item, MedicineBatch $batch, int $quantity = 2): array
    {
        return ['prescription_id' => $prescription->id, 'request_key' => (string) Str::uuid(), 'items' => [['prescription_item_id' => $item->id, 'medicine_batch_id' => $batch->id, 'quantity' => $quantity]]];
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
