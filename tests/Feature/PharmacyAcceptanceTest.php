<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_reconciles_batch_balance_to_complete_movement_history(): void
    {
        $branch = Branch::where('code', 'CBE')->firstOrFail();
        $actor = User::where('email', 'admin@lotus.test')->firstOrFail();
        $batch = $this->batch($branch, 'RECON-OK', '7.000');
        $this->movement($batch, $actor, 'RECEIPT', '10.000', '10.000');
        $this->movement($batch, $actor, 'DISPENSE', '-3.000', '7.000');
        $this->signIn('admin@lotus.test');

        $this->getJson('/api/v1/pharmacy/reconciliation')->assertOk()
            ->assertJsonPath('data.batch_count', 1)->assertJsonPath('data.reconciled_count', 1)->assertJsonPath('data.discrepancy_count', 0)
            ->assertJsonPath('data.movement_totals.RECEIPT', '10.000')->assertJsonPath('data.movement_totals.DISPENSE', '-3.000')
            ->assertJsonPath('data.batches.0.ledger_balance', '7.000')->assertJsonPath('data.batches.0.latest_recorded_balance', '7.000');
        $this->get('/pharmacy/reconciliation')->assertOk()->assertSee('RECON-OK')->assertSee('Reconciled');
    }

    public function test_reconciliation_exposes_tampered_or_incomplete_batch_history(): void
    {
        $branch = Branch::where('code', 'CBE')->firstOrFail();
        $actor = User::where('email', 'admin@lotus.test')->firstOrFail();
        $batch = $this->batch($branch, 'RECON-BAD', '9.000');
        $this->movement($batch, $actor, 'RECEIPT', '10.000', '10.000');
        $this->signIn('admin@lotus.test');

        $this->getJson('/api/v1/pharmacy/reconciliation?status=DISCREPANCY')->assertOk()
            ->assertJsonPath('data.discrepancy_count', 1)->assertJsonPath('data.batches.0.batch_number', 'RECON-BAD')
            ->assertJsonPath('data.batches.0.on_hand_quantity', '9.000')->assertJsonPath('data.batches.0.ledger_balance', '10.000');
    }

    public function test_reconciliation_is_admin_only_and_branch_scoped(): void
    {
        $river = Branch::where('code', 'SLM')->firstOrFail();
        $this->batch($river, 'RIVER-PRIVATE', '5.000');
        $this->signIn('admin@lotus.test');
        $this->get('/pharmacy/reconciliation')->assertOk()->assertDontSee('RIVER-PRIVATE');
        $this->signInAs($this->pharmacist());
        $this->get('/pharmacy/reconciliation')->assertForbidden();
        $this->getJson('/api/v1/pharmacy/reconciliation')->assertForbidden();
    }

    private function batch(Branch $branch, string $number, string $onHand): MedicineBatch
    {
        $medicine = Medicine::factory()->create(['hospital_id' => $branch->hospital_id]);

        return MedicineBatch::create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'medicine_id' => $medicine->id, 'batch_number' => $number, 'manufactured_on' => now()->subMonth(), 'expires_on' => now()->addYear(), 'received_quantity' => '10.000', 'on_hand_quantity' => $onHand, 'unit_cost' => 10, 'sale_price' => 15, 'status' => 'active']);
    }

    private function movement(MedicineBatch $batch, User $actor, string $type, string $quantity, string $balance): void
    {
        $batch->movements()->create(['hospital_id' => $batch->hospital_id, 'branch_id' => $batch->branch_id, 'medicine_id' => $batch->medicine_id, 'type' => $type, 'quantity' => $quantity, 'balance_after' => $balance, 'reference_type' => MedicineBatch::class, 'reference_id' => $batch->id, 'recorded_by' => $actor->id, 'recorded_at' => now()]);
    }

    private function pharmacist(): User
    {
        $branch = Branch::where('code', 'CBE')->firstOrFail();
        $user = User::factory()->create(['hospital_id' => $branch->hospital_id]);
        Membership::create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'user_id' => $user->id, 'role_id' => Role::where('name', 'PHARMACIST')->firstOrFail()->id, 'status' => 'active']);

        return $user;
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
