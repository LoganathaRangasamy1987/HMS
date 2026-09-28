<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Hospital;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineUnit;
use App\Models\Membership;
use App\Models\PharmacyPurchase;
use App\Models\PharmacySupplier;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class PharmacyReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_pharmacist_receives_supplier_stock_with_exact_totals_batch_and_ledger(): void
    {
        [$user, $supplier, $medicine] = $this->fixture();
        $this->signInAs($user);

        $response = $this->postJson('/api/v1/pharmacy/purchases', $this->receipt($supplier, $medicine));

        $response->assertCreated()->assertJsonPath('data.number', 'PUR-1-'.now()->year.'-000001')
            ->assertJsonPath('data.subtotal', '200.00')->assertJsonPath('data.tax_amount', '10.00')->assertJsonPath('data.total', '210.00');
        $batch = MedicineBatch::firstOrFail();
        $this->assertSame('12.000', $batch->received_quantity);
        $this->assertSame('12.000', $batch->on_hand_quantity);
        $this->assertDatabaseHas('pharmacy_purchase_items', ['medicine_code' => $medicine->code, 'batch_number' => 'PCM-2401', 'total' => 210]);
        $this->assertDatabaseHas('stock_movements', ['medicine_batch_id' => $batch->id, 'type' => 'RECEIPT', 'quantity' => 12, 'balance_after' => 12, 'recorded_by' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['module' => 'pharmacy_purchases', 'action' => 'received']);
        $this->get('/pharmacy/purchases')->assertOk()->assertSee('Batch stock')->assertSee('PCM-2401');
    }

    public function test_later_receipt_accumulates_existing_batch_and_preserves_each_movement(): void
    {
        [$user, $supplier, $medicine] = $this->fixture();
        $this->signInAs($user);
        $this->postJson('/api/v1/pharmacy/purchases', $this->receipt($supplier, $medicine))->assertCreated();
        $secondSupplier = PharmacySupplier::factory()->create(['hospital_id' => $supplier->hospital_id, 'status' => 'active']);
        $second = $this->receipt($secondSupplier, $medicine, ['supplier_invoice_number' => 'INV-002', 'request_key' => (string) Str::uuid()]);
        $second['items'][0]['quantity'] = '3.000';
        $second['items'][0]['free_quantity'] = '0.000';

        $this->postJson('/api/v1/pharmacy/purchases', $second)->assertCreated();

        $this->assertSame('15.000', MedicineBatch::firstOrFail()->on_hand_quantity);
        $this->assertSame(2, StockMovement::count());
        $this->assertSame(['12.000', '15.000'], StockMovement::orderBy('id')->pluck('balance_after')->all());
    }

    public function test_request_key_replay_is_idempotent_and_changed_replay_is_rejected(): void
    {
        [$user, $supplier, $medicine] = $this->fixture();
        $this->signInAs($user);
        $payload = $this->receipt($supplier, $medicine);
        $this->postJson('/api/v1/pharmacy/purchases', $payload)->assertCreated();
        $this->postJson('/api/v1/pharmacy/purchases', $payload)->assertOk();
        $this->assertSame(1, PharmacyPurchase::count());
        $this->assertSame(1, StockMovement::count());

        $payload['items'][0]['quantity'] = '11.000';
        $this->postJson('/api/v1/pharmacy/purchases', $payload)->assertUnprocessable()->assertJsonValidationErrors('request_key');
    }

    public function test_receipts_validate_dates_tenant_references_and_permissions(): void
    {
        [$user, $supplier, $medicine] = $this->fixture();
        $this->signInAs($user);
        $invalid = $this->receipt($supplier, $medicine);
        $invalid['items'][0]['expires_on'] = now()->subDay()->toDateString();
        $this->postJson('/api/v1/pharmacy/purchases', $invalid)->assertUnprocessable()->assertJsonValidationErrors('items.0.expires_on');

        $foreign = PharmacySupplier::factory()->create(['hospital_id' => Hospital::where('code', 'RIVER')->value('id'), 'status' => 'active']);
        $invalid = $this->receipt($foreign, $medicine, ['request_key' => (string) Str::uuid()]);
        $this->postJson('/api/v1/pharmacy/purchases', $invalid)->assertUnprocessable()->assertJsonValidationErrors('pharmacy_supplier_id');
        $this->assertSame(0, PharmacyPurchase::count());

        $this->signIn('reception@lotus.test');
        $this->get('/pharmacy/purchases')->assertForbidden();
    }

    public function test_receipt_and_stock_ledger_records_are_immutable(): void
    {
        [$user, $supplier, $medicine] = $this->fixture();
        $this->signInAs($user);
        $this->postJson('/api/v1/pharmacy/purchases', $this->receipt($supplier, $medicine))->assertCreated();

        $this->expectException(LogicException::class);
        PharmacyPurchase::firstOrFail()->update(['total' => '1.00']);
    }

    public function test_purchase_detail_is_branch_scoped(): void
    {
        [$user, $supplier, $medicine] = $this->fixture();
        $this->signInAs($user);
        $id = $this->postJson('/api/v1/pharmacy/purchases', $this->receipt($supplier, $medicine))->assertCreated()->json('data.id');
        $otherBranch = Branch::where('hospital_id', $supplier->hospital_id)->where('code', '!=', 'CBE')->firstOrFail();
        $membership = Membership::create(['hospital_id' => $otherBranch->hospital_id, 'branch_id' => $otherBranch->id, 'user_id' => $user->id, 'role_id' => Role::where('name', 'PHARMACIST')->firstOrFail()->id, 'status' => 'active']);
        $this->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()])->getJson("/api/v1/pharmacy/purchases/{$id}")->assertNotFound();
    }

    /** @return array{User, PharmacySupplier, Medicine} */
    private function fixture(): array
    {
        $branch = Branch::where('code', 'CBE')->firstOrFail();
        $unit = MedicineUnit::factory()->create(['hospital_id' => $branch->hospital_id]);
        $medicine = Medicine::factory()->create(['hospital_id' => $branch->hospital_id, 'purchase_unit_id' => $unit->id, 'status' => 'active']);
        $supplier = PharmacySupplier::factory()->create(['hospital_id' => $branch->hospital_id, 'status' => 'active']);
        $user = User::factory()->create(['hospital_id' => $branch->hospital_id]);
        Membership::create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'user_id' => $user->id, 'role_id' => Role::where('name', 'PHARMACIST')->firstOrFail()->id, 'status' => 'active']);

        return [$user, $supplier, $medicine];
    }

    /** @param array<string, mixed> $overrides */
    private function receipt(PharmacySupplier $supplier, Medicine $medicine, array $overrides = []): array
    {
        return array_replace([
            'pharmacy_supplier_id' => $supplier->id,
            'supplier_invoice_number' => 'INV-001',
            'purchase_date' => now()->toDateString(),
            'request_key' => (string) Str::uuid(),
            'items' => [[
                'medicine_id' => $medicine->id, 'batch_number' => 'PCM-2401', 'manufactured_on' => now()->subMonth()->toDateString(),
                'expires_on' => now()->addYear()->toDateString(), 'quantity' => '10.000', 'free_quantity' => '2.000',
                'unit_cost' => '20.00', 'sale_price' => '25.00', 'tax_rate_percent' => '5.00',
            ]],
        ], $overrides);
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
