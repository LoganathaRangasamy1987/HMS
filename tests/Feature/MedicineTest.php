<?php

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\Medicine;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_administrator_manages_hospital_medicines(): void
    {
        $this->signIn('admin@lotus.test');
        $id = $this->postJson('/api/v1/medicines', ['code' => ' ibu400 ', 'name' => 'Ibuprofen', 'generic_name' => 'Ibuprofen', 'form' => 'Tablet', 'strength' => '400 mg', 'status' => 'active'])
            ->assertCreated()->assertJsonPath('data.code', 'IBU400')->json('data.id');
        $this->assertDatabaseHas('medicines', ['id' => $id, 'hospital_id' => Hospital::where('code', 'LOTUS')->value('id'), 'status' => 'active']);
        $this->putJson("/api/v1/medicines/{$id}", ['code' => 'IBU400', 'name' => 'Ibuprofen', 'generic_name' => 'Ibuprofen', 'form' => 'Tablet', 'strength' => '400 mg', 'status' => 'inactive'])->assertOk();
        $this->get('/medicines')->assertOk()->assertSee('Ibuprofen')->assertSee('Inactive');
    }

    public function test_catalog_is_tenant_and_role_scoped(): void
    {
        $river = Hospital::where('code', 'RIVER')->firstOrFail();
        Medicine::factory()->create(['hospital_id' => $river->id, 'name' => 'River only medicine']);
        $this->signIn('doctor@lotus.test');
        $this->getJson('/api/v1/medicines')->assertOk()->assertJsonMissing(['name' => 'River only medicine']);
        $this->postJson('/api/v1/medicines', ['code' => 'X', 'name' => 'X', 'form' => 'Tablet', 'strength' => '1 mg', 'status' => 'active'])->assertForbidden();
        $this->signIn('reception@lotus.test');
        $this->get('/medicines')->assertForbidden();
    }

    private function signIn(string $email): void
    {
        $user = User::where('email', $email)->firstOrFail();
        $membership = $user->memberships()->whereHas('branch', fn ($query) => $query->where('code', 'CBE'))->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);
    }
}
