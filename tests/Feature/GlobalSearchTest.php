<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Hospital;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_receptionist_searches_patients_and_invoices_within_active_branch(): void
    {
        $this->signIn('reception@lotus.test');
        $branch = $this->branch('CBE');
        $patient = Patient::factory()->forBranch($branch)->create(['first_name' => 'Anita', 'last_name' => 'Needle', 'mobile' => '+919876543210']);
        $invoice = $this->invoice($branch, $patient, 'INV-NEEDLE-001');
        $otherBranch = $this->branch('CHN');
        $foreignPatient = Patient::factory()->forBranch($otherBranch)->create(['first_name' => 'Foreign', 'last_name' => 'Needle']);
        $this->invoice($otherBranch, $foreignPatient, 'INV-NEEDLE-OTHER');

        $this->getJson('/api/v1/search?q=Needle')->assertOk()
            ->assertJsonPath('data.counts.patients', 1)->assertJsonPath('data.counts.invoices', 1)
            ->assertJsonPath('data.patients.0.id', $patient->id)->assertJsonPath('data.invoices.0.id', $invoice->id)
            ->assertJsonMissing(['id' => $foreignPatient->id])->assertJsonMissing(['number' => 'INV-NEEDLE-OTHER']);
        $this->get('/search?q=Needle')->assertOk()->assertSee('Global search')->assertSee($patient->uhid)->assertSee('INV-NEEDLE-001')->assertDontSee('INV-NEEDLE-OTHER');
        $this->getJson('/api/v1/search?q=9876543210&category=patients')->assertOk()->assertJsonPath('data.patients.0.id', $patient->id)->assertJsonCount(0, 'data.invoices');
    }

    public function test_category_permissions_and_csv_export_use_the_same_scope(): void
    {
        $branch = $this->branch('CBE');
        $patient = Patient::factory()->forBranch($branch)->create(['first_name' => 'Export', 'last_name' => 'Needle']);
        $this->invoice($branch, $patient, 'INV-EXPORT-NEEDLE');

        $this->signIn('doctor@lotus.test');
        $this->getJson('/api/v1/search?q=Needle')->assertOk()->assertJsonPath('data.counts.patients', 1)->assertJsonPath('data.counts.invoices', 0);
        $this->get('/search?q=Needle&category=invoices')->assertForbidden();
        $this->get('/search/export?q=Needle&category=invoices')->assertForbidden();

        $this->signIn('reception@lotus.test');
        $export = $this->get('/search/export?q=Needle');
        $export->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->assertDownload('search-results.csv');
        $content = $export->streamedContent();
        $this->assertStringContainsString($patient->uhid, $content);
        $this->assertStringContainsString('INV-EXPORT-NEEDLE', $content);

        $pharmacist = User::factory()->create(['hospital_id' => $branch->hospital_id]);
        Membership::create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'user_id' => $pharmacist->id, 'role_id' => Role::where('name', 'PHARMACIST')->firstOrFail()->id, 'status' => 'active']);
        $this->signInAs($pharmacist);
        $this->get('/search')->assertForbidden();
        $this->getJson('/api/v1/search?q=Needle')->assertForbidden();
    }

    public function test_search_and_export_are_bounded_and_validate_queries(): void
    {
        $this->signIn('reception@lotus.test');
        $branch = $this->branch('CBE');
        Patient::factory()->count(3)->forBranch($branch)->create(['last_name' => 'Bounded']);

        $this->getJson('/api/v1/search?q=Bounded&category=patients&limit=2')->assertOk()->assertJsonCount(2, 'data.patients');
        $this->get('/search?q=x')->assertSessionHasErrors('q');
        $this->get('/search?q=Bounded&limit=51')->assertSessionHasErrors('limit');
        $this->get('/search/export?q=Bounded&limit=501')->assertSessionHasErrors('limit');
        $this->get('/search/export')->assertSessionHasErrors('q');
    }

    private function invoice(Branch $branch, Patient $patient, string $number): Invoice
    {
        $actor = User::where('email', 'admin@lotus.test')->firstOrFail();

        return Invoice::factory()->create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'patient_id' => $patient->id, 'created_by' => $actor->id, 'number' => $number, 'status' => 'ISSUED', 'total' => '250.00', 'issued_by' => $actor->id, 'issued_at' => now()]);
    }

    private function branch(string $code): Branch
    {
        return Branch::where('hospital_id', Hospital::where('code', 'LOTUS')->value('id'))->where('code', $code)->firstOrFail();
    }

    private function signIn(string $email, string $branchCode = 'CBE'): void
    {
        $user = User::where('email', $email)->firstOrFail();
        $membership = $user->memberships()->whereHas('branch', fn ($query) => $query->where('code', $branchCode))->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);
    }

    private function signInAs(User $user): void
    {
        $membership = $user->memberships()->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);
    }
}
