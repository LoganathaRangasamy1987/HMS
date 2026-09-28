<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FinancialAdjustment;
use App\Models\Hospital;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_reconciles_branch_local_day_by_mode_and_refunds(): void
    {
        $this->signIn('admin@lotus.test');
        $branch = $this->branch('CBE');
        $actor = User::where('email', 'admin@lotus.test')->firstOrFail();
        $invoice = $this->issuedInvoice($branch, $actor, '500.00');
        $cash = $this->payment($invoice, $actor, '100.25', 'CASH', '2026-09-24 02:00:00');
        $this->payment($invoice, $actor, '50.50', 'UPI', '2026-09-24 17:59:59', 'UPI-RECON-1');
        $this->payment($invoice, $actor, '999.00', 'CASH', '2026-09-23 18:29:59');
        FinancialAdjustment::factory()->create([
            'hospital_id' => $branch->hospital_id,
            'branch_id' => $branch->id,
            'invoice_id' => $invoice->id,
            'payment_id' => $cash->id,
            'type' => 'REFUND',
            'amount' => '20.25',
            'reason' => 'Reconciliation test refund',
            'recorded_at' => '2026-09-24 10:00:00',
            'recorded_by' => $actor->id,
        ]);
        $otherBranch = $this->branch('CHN');
        $this->payment($this->issuedInvoice($otherBranch, $actor, '700.00'), $actor, '700.00', 'CARD', '2026-09-24 08:00:00', 'OTHER-BRANCH');

        $this->getJson('/api/v1/billing/reconciliation?date=2026-09-24')
            ->assertOk()
            ->assertJsonPath('data.timezone', 'Asia/Kolkata')
            ->assertJsonPath('data.gross_collected', '150.75')
            ->assertJsonPath('data.refunded', '20.25')
            ->assertJsonPath('data.net_collected', '130.50')
            ->assertJsonPath('data.payment_count', 2)
            ->assertJsonPath('data.refund_count', 1)
            ->assertJsonPath('data.modes.CASH.total', '100.25')
            ->assertJsonPath('data.modes.UPI.total', '50.50');
        $this->get('/billing/reconciliation?date=2026-09-24')
            ->assertOk()
            ->assertSee('Daily reconciliation')
            ->assertSee('₹150.75')
            ->assertSee('₹20.25')
            ->assertSee('₹130.50')
            ->assertSee('Reconciliation test refund')
            ->assertDontSee('OTHER-BRANCH');
    }

    public function test_reconciliation_requires_admin_and_valid_date(): void
    {
        $this->signIn('reception@lotus.test');
        $this->get('/billing/reconciliation')->assertForbidden();
        $this->getJson('/api/v1/billing/reconciliation')->assertForbidden();
        $this->signIn('doctor@lotus.test');
        $this->get('/billing/reconciliation')->assertForbidden();
        $this->signIn('admin@lotus.test');
        $this->get('/billing/reconciliation?date=not-a-date')->assertSessionHasErrors('date');
    }

    public function test_failed_and_replayed_payments_leave_one_reconciled_entry(): void
    {
        $this->signIn('admin@lotus.test');
        $branch = $this->branch('CBE');
        $actor = User::where('email', 'admin@lotus.test')->firstOrFail();
        $invoice = $this->issuedInvoice($branch, $actor, '100.00');
        $requestKey = (string) Str::uuid();

        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", ['amount' => '100.01', 'mode' => 'CASH', 'request_key' => (string) Str::uuid()])
            ->assertUnprocessable();
        $payload = ['amount' => '100.00', 'mode' => 'CASH', 'request_key' => $requestKey];
        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", $payload)->assertCreated();
        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", $payload)->assertOk()->assertJsonPath('replayed', true);

        $this->assertDatabaseCount('payments', 1);
        $this->getJson('/api/v1/billing/reconciliation?date='.now('Asia/Kolkata')->toDateString())
            ->assertOk()
            ->assertJsonPath('data.gross_collected', '100.00')
            ->assertJsonPath('data.payment_count', 1)
            ->assertJsonPath('data.net_collected', '100.00');
    }

    private function issuedInvoice(Branch $branch, User $actor, string $total): Invoice
    {
        return Invoice::factory()->create([
            'hospital_id' => $branch->hospital_id,
            'branch_id' => $branch->id,
            'patient_id' => Patient::factory()->forBranch($branch)->create()->id,
            'created_by' => $actor->id,
            'status' => 'ISSUED',
            'number' => 'RECON-'.Str::uuid(),
            'total' => $total,
            'issued_by' => $actor->id,
            'issued_at' => now(),
        ]);
    }

    private function payment(Invoice $invoice, User $actor, string $amount, string $mode, string $receivedAt, ?string $reference = null): Payment
    {
        return Payment::factory()->create([
            'hospital_id' => $invoice->hospital_id,
            'branch_id' => $invoice->branch_id,
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'mode' => $mode,
            'reference' => $reference,
            'received_at' => $receivedAt,
            'received_by' => $actor->id,
        ]);
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
