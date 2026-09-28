<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\LabTest;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class LabCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_administrator_creates_lookups_and_active_versioned_test(): void
    {
        $this->signIn('admin@lotus.test');
        [$category, $sample, $unit] = $this->lookups();
        $testId = $this->postJson('/api/v1/laboratory/tests', $this->payload($category, $sample, $unit))
            ->assertCreated()->assertJsonPath('data.code', 'CBC')->assertJsonPath('data.active_version.version', 1)
            ->assertJsonPath('data.active_version.status', 'active')->assertJsonPath('data.active_version.currency', 'INR')
            ->assertJsonPath('data.active_version.parameters.0.reference_min', '12.0000')->json('data.id');

        $this->assertDatabaseHas('lab_tests', ['id' => $testId, 'code' => 'CBC', 'status' => 'active']);
        $this->assertDatabaseHas('lab_test_versions', ['lab_test_id' => $testId, 'version' => 1, 'price' => '450.00', 'status' => 'active']);
        $this->assertDatabaseHas('lab_test_parameters', ['code' => 'HB', 'unit_id' => $unit, 'sort_order' => 1]);
        $this->get('/laboratory/catalog')->assertOk()->assertSee('Complete blood count')->assertSee('₹450.00');
        $this->assertSame(5, AuditLog::whereIn('module', ['lab_categories', 'lab_sample_types', 'lab_units', 'lab_tests', 'lab_test_versions'])->count());
    }

    public function test_draft_activation_preserves_previous_version_and_switches_active_definition(): void
    {
        $this->signIn('admin@lotus.test');
        [$category, $sample, $unit] = $this->lookups();
        $testId = $this->postJson('/api/v1/laboratory/tests', $this->payload($category, $sample, $unit))->json('data.id');
        $draft = $this->postJson("/api/v1/laboratory/tests/{$testId}/versions", [
            ...$this->versionPayload($category, $sample, $unit),
            'price' => '500.00',
            'parameters' => [['code' => 'HB', 'name' => 'Haemoglobin revised', 'result_type' => 'NUMERIC', 'unit_id' => $unit, 'reference_min' => '11.5', 'reference_max' => '16.5', 'reference_text' => 'Adult reference']],
        ])->assertCreated()->assertJsonPath('data.version', 2)->assertJsonPath('data.status', 'draft')->json('data.id');

        $this->assertDatabaseHas('lab_test_versions', ['lab_test_id' => $testId, 'version' => 1, 'status' => 'active']);
        $this->postJson("/api/v1/laboratory/tests/{$testId}/versions/{$draft}/activate")->assertOk()->assertJsonPath('data.status', 'active');
        $this->assertDatabaseHas('lab_test_versions', ['lab_test_id' => $testId, 'version' => 1, 'price' => '450.00', 'status' => 'archived']);
        $this->assertDatabaseHas('lab_test_parameters', ['lab_test_version_id' => $draft, 'name' => 'Haemoglobin revised']);
        $this->assertSame($draft, LabTest::findOrFail($testId)->active_version_id);
        $this->postJson("/api/v1/laboratory/tests/{$testId}/versions/{$draft}/activate")->assertNotFound();
    }

    public function test_versioned_definition_content_is_immutable(): void
    {
        $this->signIn('admin@lotus.test');
        [$category, $sample, $unit] = $this->lookups();
        $testId = $this->postJson('/api/v1/laboratory/tests', $this->payload($category, $sample, $unit))->json('data.id');
        $version = LabTest::findOrFail($testId)->activeVersion;
        $this->expectException(LogicException::class);
        $version->update(['price' => '1.00']);
    }

    public function test_catalog_validation_rejects_invalid_ranges_duplicates_and_foreign_lookups(): void
    {
        $this->signIn('admin@lotus.test');
        [$category, $sample, $unit] = $this->lookups();
        $this->postJson('/api/v1/laboratory/tests', [
            ...$this->payload($category, $sample, $unit),
            'parameters' => [
                ['code' => 'HB', 'name' => 'One', 'result_type' => 'NUMERIC', 'reference_min' => '20', 'reference_max' => '10'],
                ['code' => 'hb', 'name' => 'Two', 'result_type' => 'TEXT'],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('parameters.1.code');
        $this->postJson('/api/v1/laboratory/tests', [
            ...$this->payload($category, $sample, $unit),
            'parameters' => [['code' => 'TEXT', 'name' => 'Comment', 'result_type' => 'TEXT', 'unit_id' => $unit]],
        ])->assertUnprocessable()->assertJsonValidationErrors('parameters.0.result_type');
        $riverCategory = $this->postLookup('categories', 'RIVER-CAT', 'River category', null, 'admin@river.test', 'SLM');
        $this->signIn('admin@lotus.test');
        $this->postJson('/api/v1/laboratory/tests', [...$this->payload($category, $sample, $unit), 'category_id' => $riverCategory])
            ->assertUnprocessable()->assertJsonValidationErrors('category_id');
        $this->assertDatabaseCount('lab_tests', 0);
    }

    public function test_clinical_and_reception_roles_can_view_active_catalog_but_cannot_manage_it(): void
    {
        $this->signIn('admin@lotus.test');
        [$category, $sample, $unit] = $this->lookups();
        $testId = $this->postJson('/api/v1/laboratory/tests', $this->payload($category, $sample, $unit))->json('data.id');
        foreach (['doctor@lotus.test', 'reception@lotus.test'] as $email) {
            $this->signIn($email);
            $this->getJson('/api/v1/laboratory/catalog')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $testId);
            $this->postJson('/api/v1/laboratory/categories', ['code' => 'NO', 'name' => 'Denied'])->assertForbidden();
            $this->postJson("/api/v1/laboratory/tests/{$testId}/versions", $this->versionPayload($category, $sample, $unit))->assertForbidden();
        }
        $this->signIn('admin@lotus.test');
        $this->putJson("/api/v1/laboratory/tests/{$testId}", ['name' => 'Complete blood count', 'status' => 'inactive'])->assertOk();
        $this->signIn('doctor@lotus.test');
        $this->getJson('/api/v1/laboratory/catalog')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_catalog_is_hospital_scoped(): void
    {
        $this->signIn('admin@lotus.test');
        [$category, $sample, $unit] = $this->lookups();
        $testId = $this->postJson('/api/v1/laboratory/tests', $this->payload($category, $sample, $unit))->json('data.id');
        $this->signIn('admin@river.test', 'SLM');
        $this->getJson('/api/v1/laboratory/catalog')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/laboratory/tests/{$testId}")->assertNotFound();
        $this->putJson("/api/v1/laboratory/tests/{$testId}", ['name' => 'Foreign change', 'status' => 'inactive'])->assertNotFound();
    }

    /** @return array{int, int, int} */
    private function lookups(): array
    {
        return [
            $this->postLookup('categories', 'HAEM', 'Haematology'),
            $this->postLookup('sample-types', 'EDTA', 'EDTA whole blood'),
            $this->postLookup('units', 'GDL', 'Grams per decilitre', 'g/dL'),
        ];
    }

    private function postLookup(string $type, string $code, string $name, ?string $symbol = null, ?string $email = null, string $branch = 'CBE'): int
    {
        if ($email) {
            $this->signIn($email, $branch);
        }

        return $this->postJson("/api/v1/laboratory/{$type}", array_filter(compact('code', 'name', 'symbol'), fn ($value) => $value !== null))->assertCreated()->json('data.id');
    }

    /** @return array<string, mixed> */
    private function payload(int $category, int $sample, int $unit): array
    {
        return ['code' => 'cbc', 'name' => 'Complete blood count', ...$this->versionPayload($category, $sample, $unit)];
    }

    /** @return array<string, mixed> */
    private function versionPayload(int $category, int $sample, int $unit): array
    {
        return [
            'category_id' => $category, 'sample_type_id' => $sample, 'sample_volume' => '2 mL',
            'instructions' => 'Mix gently after collection.', 'price' => '450.00',
            'parameters' => [['code' => 'HB', 'name' => 'Haemoglobin', 'result_type' => 'NUMERIC', 'unit_id' => $unit, 'reference_min' => '12', 'reference_max' => '16', 'reference_text' => 'Adult reference']],
        ];
    }

    private function signIn(string $email, string $branchCode = 'CBE'): void
    {
        $user = User::where('email', $email)->firstOrFail();
        $membership = $user->memberships()->whereHas('branch', fn ($query) => $query->where('code', $branchCode))->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);
    }
}
