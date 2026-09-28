<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\ServiceItem;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_partial_and_final_payments_update_exact_balance_and_replay_once(): void
    {
        $this->signIn('reception@lotus.test');
        $invoice = $this->issueInvoice();
        $first = $this->payment('40.01', 'CASH');
        $this->postJson("/api/v1/invoices/{$invoice}/payments", $first)->assertCreated()->assertJsonPath('invoice.status', 'PARTIALLY_PAID')->assertJsonPath('invoice.paid_total', '40.01')->assertJsonPath('invoice.balance', '66.19');
        $this->postJson("/api/v1/invoices/{$invoice}/payments", $first)->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('invoice.balance', '66.19');
        $this->assertDatabaseCount('payments', 1);
        $final = $this->payment('66.19', 'UPI', 'UPI-TEST-1');
        $this->postJson("/api/v1/invoices/{$invoice}/payments", $final)->assertCreated()->assertJsonPath('invoice.status', 'PAID')->assertJsonPath('invoice.paid_total', '106.20')->assertJsonPath('invoice.balance', '0.00');
        $this->postJson("/api/v1/invoices/{$invoice}/payments", $final)->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('invoice.balance', '0.00');
        $this->getJson("/api/v1/invoices/{$invoice}")->assertOk()->assertJsonPath('payment_summary.balance', '0.00')->assertJsonCount(2, 'data.payments');
        $this->get("/invoices/{$invoice}")->assertOk()->assertSee('Payments')->assertSee('66.19')->assertDontSee('Record payment');
        $this->assertDatabaseCount('payments', 2);
        $this->assertSame(2, AuditLog::where('module', 'payments')->where('action', 'recorded')->count());
        $this->expectException(LogicException::class);
        Payment::firstOrFail()->delete();
    }

    public function test_overpayment_invalid_modes_and_changed_request_key_do_not_collect(): void
    {
        $this->signIn('admin@lotus.test');
        $invoice = $this->issueInvoice();
        $this->postJson("/api/v1/invoices/{$invoice}/payments", $this->payment('106.21', 'CASH'))->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->postJson("/api/v1/invoices/{$invoice}/payments", $this->payment('0.00', 'CASH'))->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->postJson("/api/v1/invoices/{$invoice}/payments", $this->payment('10.00', 'UPI'))->assertUnprocessable()->assertJsonValidationErrors('reference');
        $this->postJson("/api/v1/invoices/{$invoice}/payments", $this->payment('10.00', 'OTHER'))->assertUnprocessable()->assertJsonValidationErrors('mode');
        $this->postJson("/api/v1/invoices/{$invoice}/payments", ['amount' => '10.00', 'mode' => 'CASH'])->assertUnprocessable()->assertJsonValidationErrors('request_key');
        $this->assertDatabaseCount('payments', 0);
        $first = $this->payment('20.00', 'CARD', 'CARD-TEST-1');
        $this->postJson("/api/v1/invoices/{$invoice}/payments", $first)->assertCreated();
        $this->postJson("/api/v1/invoices/{$invoice}/payments", [...$first, 'amount' => '21.00'])->assertUnprocessable()->assertJsonValidationErrors('request_key');
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('invoices', ['id' => $invoice, 'status' => 'PARTIALLY_PAID']);
    }

    public function test_draft_cross_branch_foreign_hospital_and_doctor_cannot_collect(): void
    {
        $this->signIn('admin@lotus.test');
        $draft = $this->createDraft();
        $this->postJson("/api/v1/invoices/{$draft}/payments", $this->payment('10.00', 'CASH'))->assertUnprocessable()->assertJsonValidationErrors('invoice');
        $this->postJson("/api/v1/invoices/{$draft}/issue")->assertOk();
        $this->signIn('admin@lotus.test', 'CHN');
        $this->postJson("/api/v1/invoices/{$draft}/payments", $this->payment('10.00', 'CASH'))->assertNotFound();
        $this->signIn('admin@river.test', 'SLM');
        $this->postJson("/api/v1/invoices/{$draft}/payments", $this->payment('10.00', 'CASH'))->assertNotFound();
        $this->signIn('doctor@lotus.test');
        $this->postJson("/api/v1/invoices/{$draft}/payments", $this->payment('10.00', 'CASH'))->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }

    private function issueInvoice(): int
    {
        $id = $this->createDraft();
        $this->postJson("/api/v1/invoices/{$id}/issue")->assertOk();

        return $id;
    }

    private function createDraft(): int
    {
        $branch = Branch::where('hospital_id', Hospital::where('code', 'LOTUS')->value('id'))->where('code', 'CBE')->firstOrFail();
        $patient = Patient::factory()->forBranch($branch)->create();
        $service = ServiceItem::factory()->create(['hospital_id' => $branch->hospital_id, 'base_price' => '100.00', 'discount_type' => 'percentage', 'discount_value' => '10.00', 'tax_rate_percent' => '18.00']);

        return $this->postJson('/api/v1/invoices', ['patient_id' => $patient->id, 'lines' => [['service_item_id' => $service->id, 'quantity' => 1]]])->assertCreated()->json('data.id');
    }

    private function payment(string $amount, string $mode, ?string $reference = null): array
    {
        return ['amount' => $amount, 'mode' => $mode, 'reference' => $reference, 'request_key' => (string) Str::uuid()];
    }

    private function signIn(string $email, string $branchCode = 'CBE'): void
    {
        $user = User::where('email', $email)->firstOrFail();
        $membership = $user->memberships()->whereHas('branch', fn ($query) => $query->where('code', $branchCode))->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);
    }
}
