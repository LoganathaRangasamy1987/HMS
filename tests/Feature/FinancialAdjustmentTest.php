<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\FinancialAdjustment;
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

class FinancialAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_credit_and_debit_adjustments_recalculate_balance_and_replay_once(): void
    {
        $this->signIn('admin@lotus.test');
        $invoice = $this->issueInvoice();
        $credit = $this->action('6.20', 'Billing correction', ['type' => 'CREDIT']);

        $this->postJson("/api/v1/invoices/{$invoice}/adjustments", $credit)
            ->assertCreated()->assertJsonPath('invoice.adjusted_total', '100.00')->assertJsonPath('invoice.balance', '100.00');
        $this->postJson("/api/v1/invoices/{$invoice}/adjustments", $credit)
            ->assertOk()->assertJsonPath('replayed', true);

        $debit = $this->action('10.00', 'Additional approved charge', ['type' => 'DEBIT']);
        $this->postJson("/api/v1/invoices/{$invoice}/adjustments", $debit)
            ->assertCreated()->assertJsonPath('invoice.adjusted_total', '110.00')->assertJsonPath('invoice.balance', '110.00');
        $this->get("/invoices/{$invoice}")->assertOk()->assertSee('Administrator financial action')->assertSee('Billing correction')->assertSee('Additional approved charge');

        $this->assertDatabaseCount('financial_adjustments', 2);
        $this->assertDatabaseHas('invoices', ['id' => $invoice, 'total' => '106.20', 'status' => 'ISSUED']);
        $this->postJson("/api/v1/invoices/{$invoice}/adjustments", [...$credit, 'amount' => '5.00'])
            ->assertUnprocessable()->assertJsonValidationErrors('request_key');
    }

    public function test_refunds_are_tied_to_original_payment_and_cannot_exceed_unrefunded_amount(): void
    {
        $this->signIn('admin@lotus.test');
        $invoice = $this->issueInvoice();
        $payment = $this->postJson("/api/v1/invoices/{$invoice}/payments", $this->payment('60.00'))->assertCreated()->json('data.id');
        $refund = $this->action('20.00', 'Duplicate patient payment');

        $this->postJson("/api/v1/invoices/{$invoice}/payments/{$payment}/refunds", $refund)
            ->assertCreated()->assertJsonPath('invoice.gross_paid', '60.00')->assertJsonPath('invoice.refunded_total', '20.00')
            ->assertJsonPath('invoice.net_paid', '40.00')->assertJsonPath('invoice.balance', '66.20')->assertJsonPath('invoice.status', 'PARTIALLY_PAID');
        $this->postJson("/api/v1/invoices/{$invoice}/payments/{$payment}/refunds", $refund)->assertOk()->assertJsonPath('replayed', true);
        $this->postJson("/api/v1/invoices/{$invoice}/payments/{$payment}/refunds", $this->action('40.01', 'Excess refund'))
            ->assertUnprocessable()->assertJsonValidationErrors('amount');

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('financial_adjustments', 1);
        $this->assertSame('60.00', Payment::findOrFail($payment)->amount);
        $this->assertSame(1, AuditLog::where('module', 'financial_adjustments')->where('action', 'refund')->count());
    }

    public function test_unpaid_invoice_can_be_voided_but_paid_or_adjusted_invoice_cannot(): void
    {
        $this->signIn('admin@lotus.test');
        $invoice = $this->issueInvoice();
        $void = $this->action(null, 'Service was not delivered');
        $this->postJson("/api/v1/invoices/{$invoice}/void", $void)
            ->assertCreated()->assertJsonPath('invoice.status', 'VOID')->assertJsonPath('invoice.adjusted_total', '0.00')->assertJsonPath('invoice.balance', '0.00');
        $this->postJson("/api/v1/invoices/{$invoice}/void", $void)->assertOk()->assertJsonPath('replayed', true);
        $this->postJson("/api/v1/invoices/{$invoice}/payments", $this->payment('1.00'))->assertUnprocessable()->assertJsonValidationErrors('invoice');

        $paidInvoice = $this->issueInvoice();
        $this->postJson("/api/v1/invoices/{$paidInvoice}/payments", $this->payment('10.00'))->assertCreated();
        $this->postJson("/api/v1/invoices/{$paidInvoice}/void", $this->action(null, 'Incorrect invoice'))
            ->assertUnprocessable()->assertJsonValidationErrors('invoice');

        $this->assertDatabaseHas('invoices', ['id' => $invoice, 'total' => '106.20', 'status' => 'VOID']);
        $this->expectException(LogicException::class);
        FinancialAdjustment::where('invoice_id', $invoice)->firstOrFail()->delete();
    }

    public function test_actions_require_reason_admin_permission_and_active_tenant_scope(): void
    {
        $this->signIn('admin@lotus.test');
        $invoice = $this->issueInvoice();
        $this->postJson("/api/v1/invoices/{$invoice}/adjustments", ['type' => 'CREDIT', 'amount' => '1.00', 'request_key' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson("/api/v1/invoices/{$invoice}/adjustments", $this->action('106.21', 'Excess credit', ['type' => 'CREDIT']))
            ->assertUnprocessable()->assertJsonValidationErrors('amount');

        $this->signIn('reception@lotus.test');
        $this->get("/invoices/{$invoice}")->assertOk()->assertDontSee('Administrator financial action')->assertDontSee('Void invoice');
        $this->postJson("/api/v1/invoices/{$invoice}/void", $this->action(null, 'Reception attempt'))->assertForbidden();
        $this->signIn('admin@lotus.test', 'CHN');
        $this->postJson("/api/v1/invoices/{$invoice}/void", $this->action(null, 'Other branch'))->assertNotFound();
        $this->signIn('admin@river.test', 'SLM');
        $this->postJson("/api/v1/invoices/{$invoice}/void", $this->action(null, 'Other hospital'))->assertNotFound();
        $this->assertDatabaseCount('financial_adjustments', 0);
    }

    private function issueInvoice(): int
    {
        $branch = Branch::where('hospital_id', Hospital::where('code', 'LOTUS')->value('id'))->where('code', 'CBE')->firstOrFail();
        $patient = Patient::factory()->forBranch($branch)->create();
        $service = ServiceItem::factory()->create(['hospital_id' => $branch->hospital_id, 'base_price' => '100.00', 'discount_type' => 'percentage', 'discount_value' => '10.00', 'tax_rate_percent' => '18.00']);
        $invoice = $this->postJson('/api/v1/invoices', ['patient_id' => $patient->id, 'lines' => [['service_item_id' => $service->id, 'quantity' => 1]]])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/invoices/{$invoice}/issue")->assertOk();

        return $invoice;
    }

    /** @param array<string, string> $extra
     * @return array<string, string|null>
     */
    private function action(?string $amount, string $reason, array $extra = []): array
    {
        return array_filter(['amount' => $amount, 'reason' => $reason, 'request_key' => (string) Str::uuid(), ...$extra], fn ($value) => $value !== null);
    }

    /** @return array<string, string> */
    private function payment(string $amount): array
    {
        return ['amount' => $amount, 'mode' => 'CASH', 'request_key' => (string) Str::uuid()];
    }

    private function signIn(string $email, string $branchCode = 'CBE'): void
    {
        $user = User::where('email', $email)->firstOrFail();
        $membership = $user->memberships()->whereHas('branch', fn ($query) => $query->where('code', $branchCode))->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);
    }
}
