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

class PharmacyWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_pharmacist_sees_branch_stock_statuses_and_can_search_by_batch(): void
    {
        [$user, $branch] = $this->pharmacist();
        $inStock = $this->medicine($branch, 'IN-STOCK', 'Healthy Stock', '5.000');
        $lowStock = $this->medicine($branch, 'LOW-STOCK', 'Low Stock', '10.000');
        $out = Medicine::factory()->create(['hospital_id' => $branch->hospital_id, 'code' => 'OUT-STOCK', 'name' => 'Out Stock', 'reorder_level' => '3.000']);
        $this->batch($branch, $inStock, 'BATCH-SEARCH', 20, now()->addYear());
        $this->batch($branch, $lowStock, 'LOW-BATCH', 5, now()->addYear());
        $this->signInAs($user);

        $this->get('/pharmacy')->assertOk()->assertSee('Healthy Stock')->assertSee('Low Stock')->assertSee('Out Stock')
            ->assertSee('In stock')->assertSee('Low stock')->assertSee('Out of stock');
        $this->get('/pharmacy?q=BATCH-SEARCH')->assertOk()->assertSee('Healthy Stock')->assertDontSee('Low Stock');
        $this->get('/pharmacy?stock_status=low_stock')->assertOk()->assertSee('Low Stock')->assertDontSee('Healthy Stock')->assertDontSee('Out Stock');
        $response = $this->getJson('/api/v1/pharmacy')->assertOk()->assertJsonPath('data.summary.low_stock_count', 1);
        $this->assertGreaterThanOrEqual(1, $response->json('data.summary.out_of_stock_count'));
    }

    public function test_expiry_alerts_use_the_configured_branch_window(): void
    {
        [$user, $branch] = $this->pharmacist();
        $medicine = $this->medicine($branch, 'EXPIRY', 'Expiry Medicine', '1.000');
        $this->batch($branch, $medicine, 'EXPIRED-001', 2, now()->subDay());
        $this->batch($branch, $medicine, 'SOON-001', 2, now()->addDays(30));
        $this->batch($branch, $medicine, 'LATER-001', 2, now()->addDays(120));
        $this->signInAs($user);

        $this->get('/pharmacy')->assertOk()->assertSee('EXPIRED-001')->assertSee('SOON-001')->assertDontSee('LATER-001')
            ->assertSee('Expired')->assertSee('Expires in 30 days');
    }

    public function test_administrator_configures_expiry_window_and_change_is_audited(): void
    {
        $branch = Branch::where('code', 'CBE')->firstOrFail();
        $this->signIn('admin@lotus.test');
        $this->putJson('/api/v1/pharmacy/settings', ['pharmacy_expiry_warning_days' => 180])->assertOk()->assertJsonPath('data.pharmacy_expiry_warning_days', 180);
        $this->assertSame(180, $branch->fresh()->pharmacy_expiry_warning_days);
        $this->assertDatabaseHas('audit_logs', ['module' => 'pharmacy_settings', 'action' => 'updated', 'record_id' => $branch->id]);

        [$pharmacist] = $this->pharmacist();
        $this->signInAs($pharmacist);
        $this->putJson('/api/v1/pharmacy/settings', ['pharmacy_expiry_warning_days' => 30])->assertForbidden();
    }

    public function test_workspace_is_role_and_branch_scoped(): void
    {
        [$user, $branch] = $this->pharmacist();
        $foreignBranch = Branch::where('code', 'SLM')->firstOrFail();
        $foreignMedicine = $this->medicine($foreignBranch, 'RIVER-MED', 'River Medicine', '1.000');
        $this->batch($foreignBranch, $foreignMedicine, 'RIVER-BATCH', 10, now()->addDays(10));
        $this->signInAs($user);
        $this->get('/pharmacy')->assertOk()->assertDontSee('River Medicine')->assertDontSee('RIVER-BATCH');

        $this->signIn('reception@lotus.test');
        $this->get('/pharmacy')->assertForbidden();
    }

    /** @return array{User, Branch} */
    private function pharmacist(): array
    {
        $branch = Branch::where('code', 'CBE')->firstOrFail();
        $user = User::factory()->create(['hospital_id' => $branch->hospital_id]);
        Membership::create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'user_id' => $user->id, 'role_id' => Role::where('name', 'PHARMACIST')->firstOrFail()->id, 'status' => 'active']);

        return [$user, $branch];
    }

    private function medicine(Branch $branch, string $code, string $name, string $reorder): Medicine
    {
        return Medicine::factory()->create(['hospital_id' => $branch->hospital_id, 'code' => $code, 'name' => $name, 'reorder_level' => $reorder, 'status' => 'active']);
    }

    private function batch(Branch $branch, Medicine $medicine, string $number, int $quantity, mixed $expiry): MedicineBatch
    {
        return MedicineBatch::create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'medicine_id' => $medicine->id, 'batch_number' => $number, 'manufactured_on' => now()->subMonth(), 'expires_on' => $expiry, 'received_quantity' => $quantity, 'on_hand_quantity' => $quantity, 'unit_cost' => 10, 'sale_price' => 15, 'status' => 'active']);
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
