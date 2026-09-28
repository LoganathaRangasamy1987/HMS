<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Hospital;
use App\Models\MedicineType;
use App\Models\MedicineUnit;
use App\Models\Membership;
use App\Models\PharmacyManufacturer;
use App\Models\PharmacySupplier;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_administrator_builds_pharmacy_lookup_and_supplier_catalogs(): void
    {
        $this->signIn('admin@lotus.test');
        $manufacturerId = $this->postJson('/api/v1/pharmacy/manufacturers', ['name' => 'Lotus Pharma'])->assertCreated()->json('data.id');
        $typeId = $this->postJson('/api/v1/pharmacy/medicine-types', ['name' => 'Tablet'])->assertCreated()->json('data.id');
        $unitId = $this->postJson('/api/v1/pharmacy/medicine-units', ['name' => 'Strip', 'symbol' => 'STR'])->assertCreated()->json('data.id');
        $supplierId = $this->postJson('/api/v1/pharmacy/suppliers', $this->supplier())->assertCreated()->assertJsonPath('data.code', 'SUP-001')->json('data.id');

        $hospitalId = Hospital::where('code', 'LOTUS')->value('id');
        $this->assertDatabaseHas('pharmacy_manufacturers', ['id' => $manufacturerId, 'hospital_id' => $hospitalId]);
        $this->assertDatabaseHas('medicine_types', ['id' => $typeId, 'hospital_id' => $hospitalId]);
        $this->assertDatabaseHas('medicine_units', ['id' => $unitId, 'hospital_id' => $hospitalId, 'symbol' => 'STR']);
        $this->putJson("/api/v1/pharmacy/suppliers/{$supplierId}", [...$this->supplier(), 'name' => 'Lotus Medical Distribution', 'status' => 'inactive'])->assertOk()->assertJsonPath('data.status', 'inactive');
        $this->get('/pharmacy/catalog')->assertOk()->assertSee('Lotus Pharma')->assertSee('Lotus Medical Distribution');
        $this->assertDatabaseHas('audit_logs', ['module' => 'pharmacy_suppliers', 'action' => 'updated', 'record_id' => $supplierId]);
    }

    public function test_pharmacist_creates_classified_medicine_with_reorder_and_tax_settings(): void
    {
        $hospitalId = Hospital::where('code', 'LOTUS')->value('id');
        $manufacturer = PharmacyManufacturer::factory()->create(['hospital_id' => $hospitalId]);
        $type = MedicineType::factory()->create(['hospital_id' => $hospitalId]);
        $unit = MedicineUnit::factory()->create(['hospital_id' => $hospitalId, 'symbol' => 'STR']);
        $this->signInAs($this->pharmacist());
        $id = $this->postJson('/api/v1/medicines', [
            'code' => ' pcm500 ', 'name' => 'Paracetamol 500', 'generic_name' => 'Paracetamol',
            'pharmacy_manufacturer_id' => $manufacturer->id, 'medicine_type_id' => $type->id,
            'form' => 'Tablet', 'strength' => '500 mg', 'purchase_unit_id' => $unit->id, 'sale_unit_id' => $unit->id,
            'reorder_level' => '25.500', 'tax_rate_percent' => '5.00', 'prescription_required' => false, 'status' => 'active',
        ])->assertCreated()->assertJsonPath('data.code', 'PCM500')->json('data.id');

        $this->assertDatabaseHas('medicines', ['id' => $id, 'reorder_level' => '25.5', 'tax_rate_percent' => '5', 'prescription_required' => false]);
        $this->get('/pharmacy/catalog')->assertOk()->assertSee('Paracetamol 500')->assertSee('25.500')->assertSee('5.00%');
    }

    public function test_catalog_rejects_duplicate_and_foreign_hospital_references(): void
    {
        $this->signIn('admin@lotus.test');
        $this->postJson('/api/v1/pharmacy/manufacturers', ['name' => 'Unique Manufacturer'])->assertCreated();
        $this->postJson('/api/v1/pharmacy/manufacturers', ['name' => 'Unique Manufacturer'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $foreign = PharmacyManufacturer::factory()->create(['hospital_id' => Hospital::where('code', 'RIVER')->value('id')]);
        $this->postJson('/api/v1/medicines', [
            'code' => 'FOREIGN', 'name' => 'Foreign reference', 'pharmacy_manufacturer_id' => $foreign->id,
            'form' => 'Tablet', 'strength' => '1 mg', 'status' => 'active',
        ])->assertUnprocessable()->assertJsonValidationErrors('pharmacy_manufacturer_id');
    }

    public function test_pharmacy_catalog_permissions_and_hospital_isolation_are_enforced(): void
    {
        $riverSupplier = PharmacySupplier::factory()->create(['hospital_id' => Hospital::where('code', 'RIVER')->value('id')]);
        $pharmacist = $this->pharmacist();
        $this->signInAs($pharmacist);
        $this->getJson('/api/v1/pharmacy/catalog')->assertOk()->assertJsonCount(0, 'data.suppliers');
        $this->putJson("/api/v1/pharmacy/suppliers/{$riverSupplier->id}", $this->supplier())->assertNotFound();
        $this->signIn('doctor@lotus.test');
        $this->get('/pharmacy/catalog')->assertForbidden();
        $this->signIn('reception@lotus.test');
        $this->postJson('/api/v1/pharmacy/suppliers', $this->supplier())->assertForbidden();
    }

    private function pharmacist(): User
    {
        $branch = Branch::where('code', 'CBE')->firstOrFail();
        $user = User::factory()->create(['hospital_id' => $branch->hospital_id, 'email' => 'pharmacist@lotus.test']);
        Membership::create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'user_id' => $user->id, 'role_id' => Role::where('name', 'PHARMACIST')->firstOrFail()->id, 'status' => 'active']);

        return $user;
    }

    private function supplier(): array
    {
        return ['code' => ' sup-001 ', 'name' => 'Lotus Medical Supply', 'contact_person' => 'Demo Contact', 'phone' => '9000000000', 'email' => 'supply@example.test', 'tax_registration_number' => 'GST-DEMO-001', 'address' => 'Demo address', 'status' => 'active'];
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
