<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Hospital;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_administrator_creates_service_and_exact_quote_then_overrides_branch_price(): void
    {
        $this->signIn('admin@lotus.test');
        $id = $this->postJson('/api/v1/services', $this->payload())->assertCreated()->assertJsonPath('data.currency', 'INR')->json('data.id');
        $cbe = $this->branch('CBE');
        $chn = $this->branch('CHN');
        $this->getJson("/api/v1/services/{$id}/quote?branch_id={$cbe->id}")->assertOk()->assertJsonPath('data.total', '106.20')->assertJsonPath('data.discount_amount', '10.00')->assertJsonPath('data.tax_amount', '16.20');

        $override = ['branch_id' => $chn->id, 'base_price' => '200.00', 'tax_rate_percent' => '5.50', 'discount_type' => 'fixed', 'discount_value' => '10.00', 'is_available' => true];
        $this->putJson("/api/v1/services/{$id}/branch-price", $override)->assertCreated();
        $this->putJson("/api/v1/services/{$id}/branch-price", $override)->assertOk();
        $this->getJson("/api/v1/services/{$id}/quote?branch_id={$chn->id}")->assertOk()->assertJsonPath('data.total', '200.45');
        $this->assertDatabaseCount('service_branch_prices', 1);
        $this->assertSame(3, AuditLog::whereIn('module', ['service_items', 'service_branch_prices'])->count());
    }

    public function test_reception_sees_available_catalog_and_cannot_manage_or_query_other_branch(): void
    {
        $this->signIn('admin@lotus.test');
        $id = $this->postJson('/api/v1/services', $this->payload())->json('data.id');
        $cbe = $this->branch('CBE');
        $this->putJson("/api/v1/services/{$id}/branch-price", ['branch_id' => $cbe->id, 'is_available' => false])->assertCreated();
        $this->signIn('reception@lotus.test');
        $this->getJson('/api/v1/services')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/services/{$id}/quote")->assertUnprocessable()->assertJsonValidationErrors('service_item_id');
        $this->postJson('/api/v1/services', $this->payload())->assertForbidden();
        $this->get('/services/create')->assertForbidden();
        $this->signIn('doctor@lotus.test');
        $this->getJson('/api/v1/services')->assertForbidden();
    }

    public function test_validation_rejects_inconsistent_discounts_and_case_insensitive_duplicate_codes(): void
    {
        $this->signIn('admin@lotus.test');
        $this->postJson('/api/v1/services', [...$this->payload(), 'discount_type' => 'fixed', 'discount_value' => '100.01'])->assertUnprocessable()->assertJsonValidationErrors('discount_value');
        $this->postJson('/api/v1/services', [...$this->payload(), 'tax_rate_percent' => '101.00'])->assertUnprocessable()->assertJsonValidationErrors('tax_rate_percent');
        $id = $this->postJson('/api/v1/services', $this->payload())->assertCreated()->json('data.id');
        $this->postJson('/api/v1/services', [...$this->payload(), 'code' => 'consult-test'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->putJson("/api/v1/services/{$id}/branch-price", ['branch_id' => $this->branch('CBE')->id, 'base_price' => '5.00', 'discount_type' => 'fixed', 'discount_value' => '10.00', 'is_available' => true])->assertUnprocessable()->assertJsonValidationErrors('discount_value');
    }

    public function test_other_hospital_service_and_branch_cannot_be_used(): void
    {
        $this->signIn('admin@lotus.test');
        $id = $this->postJson('/api/v1/services', $this->payload())->json('data.id');
        $riverBranch = $this->branch('SLM', 'RIVER');
        $this->putJson("/api/v1/services/{$id}/branch-price", ['branch_id' => $riverBranch->id, 'is_available' => true])->assertUnprocessable()->assertJsonValidationErrors('branch_id');
        $this->getJson("/api/v1/services/{$id}/quote?branch_id={$riverBranch->id}")->assertNotFound();
        $this->signIn('admin@river.test', 'SLM');
        $this->getJson('/api/v1/services')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/services/{$id}")->assertNotFound();
        $this->getJson("/api/v1/services/{$id}/quote")->assertNotFound();
    }

    private function payload(): array
    {
        return ['code' => 'CONSULT-TEST', 'name' => 'Test consultation', 'type' => 'CONSULTATION', 'description' => 'Fictional test service', 'base_price' => '100.00', 'tax_rate_percent' => '18.00', 'discount_type' => 'percentage', 'discount_value' => '10.00', 'status' => 'active'];
    }

    private function branch(string $code, string $hospital = 'LOTUS'): Branch
    {
        return Branch::where('hospital_id', Hospital::where('code', $hospital)->value('id'))->where('code', $code)->firstOrFail();
    }

    private function signIn(string $email, string $branchCode = 'CBE'): void
    {
        $user = User::where('email', $email)->firstOrFail();
        $membership = $user->memberships()->whereHas('branch', fn ($query) => $query->where('code', $branchCode))->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);
    }
}
