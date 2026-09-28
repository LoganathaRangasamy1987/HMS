<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\DoctorProfile;
use App\Models\Encounter;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Membership;
use App\Models\Patient;
use App\Models\PharmacyPurchase;
use App\Models\PharmacyPurchaseItem;
use App\Models\PharmacySale;
use App\Models\PharmacySaleItem;
use App\Models\PharmacyStockAdjustment;
use App\Models\PharmacySupplier;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PharmacyStockAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_damage_request_changes_stock_only_after_different_admin_approval(): void
    {
        [$pharmacist, $batch] = $this->fixture();
        $this->signInAs($pharmacist);
        $id = $this->postJson('/api/v1/pharmacy/adjustments', $this->payload($batch, 'DAMAGED', 2))->assertCreated()->assertJsonPath('data.status', 'PENDING')->json('data.id');
        $this->assertSame('10.000', $batch->fresh()->on_hand_quantity);

        $this->signIn('admin@lotus.test');
        $this->putJson("/api/v1/pharmacy/adjustments/{$id}/decision", ['decision' => 'APPROVED'])->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->assertSame('8.000', $batch->fresh()->on_hand_quantity);
        $this->assertDatabaseHas('stock_movements', ['type' => 'DAMAGED', 'quantity' => -2, 'balance_after' => 8]);
        $this->assertDatabaseHas('audit_logs', ['module' => 'pharmacy_stock_adjustments', 'action' => 'approved']);
    }

    public function test_sale_return_restores_stock_and_cannot_exceed_source_quantity(): void
    {
        [$pharmacist, $batch] = $this->fixture();
        $saleItem = $this->saleItem($batch, $pharmacist, 3);
        $batch->update(['on_hand_quantity' => '7.000']);
        $this->signInAs($pharmacist);
        $payload = $this->payload($batch, 'SALE_RETURN', 2, ['pharmacy_sale_item_id' => $saleItem->id]);
        $id = $this->postJson('/api/v1/pharmacy/adjustments', $payload)->assertCreated()->json('data.id');
        $this->signIn('admin@lotus.test');
        $this->putJson("/api/v1/pharmacy/adjustments/{$id}/decision", ['decision' => 'APPROVED'])->assertOk();
        $this->assertSame('9.000', $batch->fresh()->on_hand_quantity);

        $this->signInAs($pharmacist);
        $payload = $this->payload($batch, 'SALE_RETURN', 2, ['pharmacy_sale_item_id' => $saleItem->id]);
        $id = $this->postJson('/api/v1/pharmacy/adjustments', $payload)->assertCreated()->json('data.id');
        $this->signIn('admin@lotus.test');
        $this->putJson("/api/v1/pharmacy/adjustments/{$id}/decision", ['decision' => 'APPROVED'])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertSame('9.000', $batch->fresh()->on_hand_quantity);
    }

    public function test_purchase_return_deducts_only_available_received_stock(): void
    {
        [$pharmacist, $batch] = $this->fixture();
        $purchaseItem = $this->purchaseItem($batch, $pharmacist, 10);
        $this->signInAs($pharmacist);
        $id = $this->postJson('/api/v1/pharmacy/adjustments', $this->payload($batch, 'PURCHASE_RETURN', 4, ['pharmacy_purchase_item_id' => $purchaseItem->id]))->assertCreated()->json('data.id');
        $this->signIn('admin@lotus.test');
        $this->putJson("/api/v1/pharmacy/adjustments/{$id}/decision", ['decision' => 'APPROVED'])->assertOk();
        $this->assertSame('6.000', $batch->fresh()->on_hand_quantity);
        $this->assertDatabaseHas('stock_movements', ['type' => 'PURCHASE_RETURN', 'quantity' => -4]);
    }

    public function test_expiry_rules_rejection_and_permissions_are_enforced(): void
    {
        [$pharmacist, $batch] = $this->fixture();
        $this->signInAs($pharmacist);
        $id = $this->postJson('/api/v1/pharmacy/adjustments', $this->payload($batch, 'EXPIRED', 1))->assertCreated()->json('data.id');
        $this->putJson("/api/v1/pharmacy/adjustments/{$id}/decision", ['decision' => 'APPROVED'])->assertForbidden();
        $this->signIn('admin@lotus.test');
        $this->putJson("/api/v1/pharmacy/adjustments/{$id}/decision", ['decision' => 'APPROVED'])->assertUnprocessable()->assertJsonValidationErrors('medicine_batch_id');
        $this->putJson("/api/v1/pharmacy/adjustments/{$id}/decision", ['decision' => 'REJECTED', 'decision_reason' => 'Batch is not expired.'])->assertOk()->assertJsonPath('data.status', 'REJECTED');
        $this->assertSame('10.000', $batch->fresh()->on_hand_quantity);
        $this->signIn('reception@lotus.test');
        $this->get('/pharmacy/adjustments')->assertForbidden();
    }

    public function test_request_replay_is_idempotent_and_source_type_must_match(): void
    {
        [$pharmacist, $batch] = $this->fixture();
        $this->signInAs($pharmacist);
        $payload = $this->payload($batch, 'DAMAGED', 1);
        $this->postJson('/api/v1/pharmacy/adjustments', $payload)->assertCreated();
        $this->postJson('/api/v1/pharmacy/adjustments', $payload)->assertOk();
        $this->assertSame(1, PharmacyStockAdjustment::count());
        $payload['quantity'] = '2.000';
        $this->postJson('/api/v1/pharmacy/adjustments', $payload)->assertUnprocessable()->assertJsonValidationErrors('request_key');
        $invalid = $this->payload($batch, 'SALE_RETURN', 1);
        $this->postJson('/api/v1/pharmacy/adjustments', $invalid)->assertUnprocessable()->assertJsonValidationErrors('type');
    }

    /** @return array{User, MedicineBatch} */
    private function fixture(): array
    {
        $branch = Branch::where('code', 'CBE')->firstOrFail();
        $medicine = Medicine::factory()->create(['hospital_id' => $branch->hospital_id]);
        $batch = MedicineBatch::create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'medicine_id' => $medicine->id, 'batch_number' => Str::random(10), 'manufactured_on' => now()->subMonth(), 'expires_on' => now()->addYear(), 'received_quantity' => '10.000', 'on_hand_quantity' => '10.000', 'unit_cost' => '20.00', 'sale_price' => '25.00', 'status' => 'active']);
        $user = User::factory()->create(['hospital_id' => $branch->hospital_id]);
        Membership::create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'user_id' => $user->id, 'role_id' => Role::where('name', 'PHARMACIST')->firstOrFail()->id, 'status' => 'active']);

        return [$user, $batch];
    }

    private function purchaseItem(MedicineBatch $batch, User $user, int $quantity): PharmacyPurchaseItem
    {
        $supplier = PharmacySupplier::factory()->create(['hospital_id' => $batch->hospital_id]);
        $purchase = PharmacyPurchase::create(['hospital_id' => $batch->hospital_id, 'branch_id' => $batch->branch_id, 'pharmacy_supplier_id' => $supplier->id, 'number' => 'PUR-TEST-'.Str::random(6), 'supplier_invoice_number' => 'SUP-'.Str::random(6), 'purchase_date' => now(), 'status' => 'RECEIVED', 'currency' => 'INR', 'subtotal' => '200.00', 'tax_amount' => '0.00', 'total' => '200.00', 'request_key' => Str::uuid(), 'payload_hash' => hash('sha256', Str::random()), 'received_by' => $user->id, 'received_at' => now()]);

        return PharmacyPurchaseItem::create(['pharmacy_purchase_id' => $purchase->id, 'medicine_id' => $batch->medicine_id, 'medicine_batch_id' => $batch->id, 'medicine_code' => $batch->medicine->code, 'medicine_name' => $batch->medicine->name, 'batch_number' => $batch->batch_number, 'manufactured_on' => $batch->manufactured_on, 'expires_on' => $batch->expires_on, 'quantity' => $quantity, 'free_quantity' => 0, 'unit_cost' => 20, 'sale_price' => 25, 'tax_rate_percent' => 0, 'subtotal' => 200, 'tax_amount' => 0, 'total' => 200]);
    }

    private function saleItem(MedicineBatch $batch, User $user, int $quantity): PharmacySaleItem
    {
        $patient = Patient::factory()->forBranch($batch->branch)->create();
        $prescriber = User::where('email', 'doctor@lotus.test')->firstOrFail();
        $doctor = DoctorProfile::factory()->create(['hospital_id' => $batch->hospital_id, 'branch_id' => $batch->branch_id, 'user_id' => $prescriber->id]);
        $encounter = Encounter::create(['hospital_id' => $batch->hospital_id, 'branch_id' => $batch->branch_id, 'patient_id' => $patient->id, 'doctor_profile_id' => $doctor->id, 'department_id' => $doctor->department_id, 'encounter_type' => 'OPD', 'status' => 'COMPLETED', 'opened_at' => now()->subHour(), 'opened_by' => $prescriber->id, 'closed_at' => now(), 'closed_by' => $prescriber->id]);
        $prescription = Prescription::create(['hospital_id' => $batch->hospital_id, 'branch_id' => $batch->branch_id, 'patient_id' => $patient->id, 'encounter_id' => $encounter->id, 'prescribed_at' => now(), 'prescribed_by' => $prescriber->id]);
        $prescriptionItem = PrescriptionItem::factory()->create(['prescription_id' => $prescription->id, 'medicine_id' => $batch->medicine_id, 'medicine_name' => $batch->medicine->name]);
        $invoice = Invoice::create(['hospital_id' => $batch->hospital_id, 'branch_id' => $batch->branch_id, 'patient_id' => $patient->id, 'created_by' => $user->id]);
        $line = $invoice->lines()->create(['service_item_id' => null, 'service_code' => $batch->medicine->code, 'description' => $batch->medicine->name, 'quantity' => $quantity, 'unit_price' => 25, 'discount_type' => 'NONE', 'discount_value' => 0, 'tax_rate_percent' => 0, 'subtotal' => 75, 'discount_amount' => 0, 'tax_amount' => 0, 'total' => 75]);
        $sale = PharmacySale::create(['hospital_id' => $batch->hospital_id, 'branch_id' => $batch->branch_id, 'patient_id' => $patient->id, 'prescription_id' => $prescription->id, 'invoice_id' => $invoice->id, 'number' => 'PHS-TEST-'.Str::random(6), 'status' => 'DISPENSED', 'currency' => 'INR', 'subtotal' => 75, 'tax_amount' => 0, 'total' => 75, 'request_key' => Str::uuid(), 'payload_hash' => hash('sha256', Str::random()), 'dispensed_by' => $user->id, 'dispensed_at' => now()]);

        return PharmacySaleItem::create(['pharmacy_sale_id' => $sale->id, 'prescription_item_id' => $prescriptionItem->id, 'medicine_id' => $batch->medicine_id, 'medicine_batch_id' => $batch->id, 'invoice_line_id' => $line->id, 'medicine_code' => $batch->medicine->code, 'medicine_name' => $batch->medicine->name, 'batch_number' => $batch->batch_number, 'expires_on' => $batch->expires_on, 'quantity' => $quantity, 'unit_price' => 25, 'tax_rate_percent' => 0, 'subtotal' => 75, 'tax_amount' => 0, 'total' => 75]);
    }

    private function payload(MedicineBatch $batch, string $type, int $quantity, array $extra = []): array
    {
        return [...$extra, 'type' => $type, 'medicine_batch_id' => $batch->id, 'quantity' => number_format($quantity, 3, '.', ''), 'reason' => 'Documented stock correction.', 'request_key' => (string) Str::uuid()];
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
