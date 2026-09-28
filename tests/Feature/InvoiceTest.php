<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\DoctorProfile;
use App\Models\Hospital;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\ServiceItem;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_draft_recalculates_and_issue_snapshots_charges_and_number(): void
    {
        $this->signIn('reception@lotus.test');
        $patient = $this->patient();
        $service = $this->service();
        $payload = $this->payload($patient, $service, 2);
        $id = $this->postJson('/api/v1/invoices', $payload)->assertCreated()->assertJsonPath('data.status', 'DRAFT')->assertJsonPath('data.total', '212.40')->json('data.id');
        $this->putJson("/api/v1/invoices/{$id}", $this->payload($patient, $service, 3))->assertOk()->assertJsonPath('data.total', '318.60');
        $issued = $this->postJson("/api/v1/invoices/{$id}/issue")->assertOk()->assertJsonPath('data.status', 'ISSUED')->json('data');
        $this->assertMatchesRegularExpression('/^INV-\d+-\d{4}-000001$/', $issued['number']);
        $this->assertSame('318.60', $issued['total']);
        $this->assertSame('300.00', $issued['lines'][0]['subtotal']);
        $this->assertSame('30.00', $issued['lines'][0]['discount_amount']);
        $this->assertSame('48.60', $issued['lines'][0]['tax_amount']);
        $service->update(['base_price' => '500.00']);
        $this->getJson("/api/v1/invoices/{$id}")->assertOk()->assertJsonPath('data.total', '318.60');
        $this->putJson("/api/v1/invoices/{$id}", $payload)->assertUnprocessable();
        $this->postJson("/api/v1/invoices/{$id}/issue")->assertUnprocessable();
        $this->assertDatabaseCount('invoice_number_sequences', 1);
        $this->assertSame(1, AuditLog::where('module', 'invoices')->where('action', 'issued')->count());
        $this->expectException(LogicException::class);
        $lockedInvoice = Invoice::findOrFail($id);
        $lockedInvoice->total = '1.00';
        $lockedInvoice->save();
    }

    public function test_appointment_must_match_patient_and_can_have_only_one_invoice(): void
    {
        $this->signIn('reception@lotus.test');
        $patient = $this->patient();
        $service = $this->service();
        $doctor = DoctorProfile::factory()->create(['branch_id' => $this->branch('CBE')->id, 'hospital_id' => $patient->hospital_id]);
        $appointment = Appointment::create(['hospital_id' => $patient->hospital_id, 'branch_id' => $this->branch('CBE')->id, 'patient_id' => $patient->id, 'doctor_profile_id' => $doctor->id, 'appointment_date' => now()->addDay()->toDateString(), 'starts_at' => '09:00', 'ends_at' => '09:15', 'token_number' => 99, 'type' => 'NEW', 'status' => 'BOOKED', 'created_by' => auth()->id()]);
        $payload = [...$this->payload($patient, $service), 'appointment_id' => $appointment->id];
        $this->postJson('/api/v1/invoices', $payload)->assertCreated();
        $this->postJson('/api/v1/invoices', $payload)->assertUnprocessable()->assertJsonValidationErrors('appointment_id');
        $otherPatient = Patient::factory()->forBranch($this->branch('CBE'))->create();
        $this->postJson('/api/v1/invoices', [...$payload, 'patient_id' => $otherPatient->id])->assertNotFound();
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_branch_hospital_and_role_boundaries_and_invalid_lines(): void
    {
        $this->signIn('reception@lotus.test');
        $patient = $this->patient();
        $service = $this->service();
        $id = $this->postJson('/api/v1/invoices', $this->payload($patient, $service))->assertCreated()->json('data.id');
        $this->postJson('/api/v1/invoices', $this->payload($patient, $service, 0))->assertUnprocessable()->assertJsonValidationErrors('lines.0.quantity');
        $this->postJson('/api/v1/invoices', $this->payload($patient, $service, 1, $service))->assertUnprocessable()->assertJsonValidationErrors('lines.1.service_item_id');
        $this->signIn('admin@lotus.test', 'CHN');
        $this->getJson("/api/v1/invoices/{$id}")->assertNotFound();
        $this->postJson("/api/v1/invoices/{$id}/issue")->assertNotFound();
        $this->signIn('admin@river.test', 'SLM');
        $this->getJson("/api/v1/invoices/{$id}")->assertNotFound();
        $this->signIn('doctor@lotus.test');
        $this->getJson('/api/v1/invoices')->assertForbidden();
        $this->postJson('/api/v1/invoices', $this->payload($patient, $service))->assertForbidden();
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_portal_form_and_second_invoice_number_work_and_foreign_service_rolls_back(): void
    {
        $this->signIn('admin@lotus.test');
        $patient = $this->patient();
        $service = $this->service();
        $this->get('/invoices/create')->assertOk()->assertSee('New invoice draft');
        $first = $this->postJson('/api/v1/invoices', $this->payload($patient, $service))->assertCreated()->json('data.id');
        $this->get("/invoices/{$first}")->assertOk()->assertSee('Invoice draft')->assertSee('106.20');
        $this->get("/invoices/{$first}/edit")->assertOk();
        $this->postJson("/api/v1/invoices/{$first}/issue")->assertOk()->assertJsonPath('data.number', sprintf('INV-%d-%d-000001', $patient->hospital_id, now('Asia/Kolkata')->year));
        $this->get("/invoices/{$first}/edit")->assertStatus(409);
        $foreignService = ServiceItem::factory()->create(['hospital_id' => Hospital::where('code', 'RIVER')->value('id')]);
        $this->postJson('/api/v1/invoices', $this->payload($patient, $foreignService))->assertNotFound();
        $this->assertDatabaseCount('invoices', 1);
        $second = $this->postJson('/api/v1/invoices', $this->payload($patient, $service))->assertCreated()->json('data.id');
        $this->postJson("/api/v1/invoices/{$second}/issue")->assertOk()->assertJsonPath('data.number', sprintf('INV-%d-%d-000002', $patient->hospital_id, now('Asia/Kolkata')->year));
    }

    public function test_billing_search_outstanding_filters_and_print_documents_are_scoped(): void
    {
        $this->signIn('admin@lotus.test');
        $patient = $this->patient();
        $service = $this->service();
        $draft = $this->postJson('/api/v1/invoices', $this->payload($patient, $service))->assertCreated()->json('data');
        $this->get("/invoices/{$draft['id']}/print")->assertStatus(409);
        $invoice = $this->postJson("/api/v1/invoices/{$draft['id']}/issue")->assertOk()->json('data');

        $this->get('/invoices?q='.urlencode($patient->uhid))
            ->assertOk()
            ->assertSee($invoice['number'])
            ->assertSee('Outstanding balances only');
        $this->get('/invoices?outstanding=1')->assertOk()->assertSee($invoice['number']);
        $this->getJson('/api/v1/invoices?q='.urlencode($patient->mobile))->assertOk()->assertJsonCount(1, 'data');
        $this->get("/invoices/{$invoice['id']}/print")
            ->assertOk()
            ->assertSee('Invoice')
            ->assertSee($invoice['number'])
            ->assertSee($patient->uhid)
            ->assertSee('Balance due');

        $payment = $this->postJson("/api/v1/invoices/{$invoice['id']}/payments", [
            'amount' => $invoice['total'],
            'mode' => 'CASH',
            'request_key' => (string) Str::uuid(),
        ])->assertCreated()->json('data');
        $this->get("/invoices/{$invoice['id']}/payments/{$payment['id']}/receipt")
            ->assertOk()
            ->assertSee('Payment receipt')
            ->assertSee('RCT-'.str_pad((string) $payment['id'], 8, '0', STR_PAD_LEFT))
            ->assertSee($invoice['number']);
        $this->get('/invoices?outstanding=1')->assertOk()->assertDontSee($invoice['number']);

        $this->signIn('admin@lotus.test', 'CHN');
        $this->get("/invoices/{$invoice['id']}/print")->assertNotFound();
        $this->get("/invoices/{$invoice['id']}/payments/{$payment['id']}/receipt")->assertNotFound();
    }

    private function payload(Patient $patient, ServiceItem $service, int $quantity = 1, ?ServiceItem $second = null): array
    {
        $lines = [['service_item_id' => $service->id, 'quantity' => $quantity]];
        if ($second) {
            $lines[] = ['service_item_id' => $second->id, 'quantity' => 1];
        }

        return ['patient_id' => $patient->id, 'lines' => $lines];
    }

    private function patient(): Patient
    {
        return Patient::factory()->forBranch($this->branch('CBE'))->create();
    }

    private function service(): ServiceItem
    {
        return ServiceItem::factory()->create(['hospital_id' => $this->branch('CBE')->hospital_id, 'base_price' => '100.00', 'discount_type' => 'percentage', 'discount_value' => '10.00', 'tax_rate_percent' => '18.00']);
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
}
