<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Consultation;
use App\Models\DoctorProfile;
use App\Models\Encounter;
use App\Models\Hospital;
use App\Models\Invoice;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PharmacySale;
use App\Models\Prescription;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationalReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_sees_branch_and_date_bounded_operational_metrics(): void
    {
        $this->signIn('admin@lotus.test');
        $branch = $this->branch('CBE');
        $actor = User::where('email', 'admin@lotus.test')->firstOrFail();
        $doctor = DoctorProfile::factory()->create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id])->load(['department', 'user']);
        $patient = Patient::factory()->forBranch($branch)->create();
        $appointment = Appointment::factory()->create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'patient_id' => $patient->id, 'doctor_profile_id' => $doctor->id, 'appointment_date' => '2026-09-20', 'status' => 'COMPLETED', 'created_by' => $actor->id]);
        $encounter = Encounter::factory()->create(['appointment_id' => $appointment->id, 'hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'patient_id' => $patient->id, 'doctor_profile_id' => $doctor->id, 'department_id' => $doctor->department_id, 'opened_at' => '2026-09-20 04:30:00', 'opened_by' => $doctor->user_id]);
        Consultation::factory()->create(['encounter_id' => $encounter->id, 'status' => 'FINALIZED', 'authored_by' => $doctor->user_id, 'finalized_by' => $doctor->user_id, 'finalized_at' => '2026-09-20 05:00:00']);
        $invoice = Invoice::factory()->create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'patient_id' => $patient->id, 'created_by' => $actor->id, 'status' => 'PARTIALLY_PAID', 'number' => 'REPORT-'.Str::uuid(), 'total' => '500.00', 'issued_by' => $actor->id, 'issued_at' => '2026-09-20 04:30:00']);
        Payment::factory()->create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'invoice_id' => $invoice->id, 'amount' => '200.00', 'received_at' => '2026-09-20 05:00:00', 'received_by' => $actor->id]);
        $labInvoice = Invoice::factory()->create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'patient_id' => $patient->id, 'created_by' => $actor->id]);
        LabOrder::factory()->create(['invoice_id' => $labInvoice->id, 'hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'patient_id' => $patient->id, 'doctor_profile_id' => $doctor->id, 'ordered_by' => $actor->id, 'ordered_at' => '2026-09-20 06:00:00']);
        $saleInvoice = Invoice::factory()->create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'patient_id' => $patient->id, 'created_by' => $actor->id]);
        $prescription = Prescription::factory()->create(['encounter_id' => $encounter->id, 'hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'patient_id' => $patient->id, 'prescribed_by' => $doctor->user_id]);
        PharmacySale::create(['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id, 'patient_id' => $patient->id, 'prescription_id' => $prescription->id, 'invoice_id' => $saleInvoice->id, 'number' => 'SALE-REPORT-1', 'status' => 'DISPENSED', 'currency' => 'INR', 'subtotal' => '100.00', 'tax_amount' => '5.00', 'total' => '105.00', 'request_key' => (string) Str::uuid(), 'payload_hash' => hash('sha256', 'report-sale'), 'dispensed_by' => $actor->id, 'dispensed_at' => '2026-09-20 07:00:00']);

        $response = $this->getJson('/api/v1/reports/operational?from=2026-09-20&to=2026-09-20');
        $response->assertOk();
        $response->assertJsonPath('data.filters.branch_id', $branch->id);
        $response->assertJsonPath('data.appointments.total', 1);
        $response->assertJsonPath('data.appointments.by_status.COMPLETED', 1);
        $response->assertJsonPath('data.consultations.total', 1);
        $response->assertJsonPath('data.consultations.finalized', 1)
            ->assertJsonPath('data.finance.revenue', '500.00')->assertJsonPath('data.finance.collected', '200.00')
            ->assertJsonPath('data.finance.receivables', '300.00')->assertJsonPath('data.departments.0.name', $doctor->department->name)
            ->assertJsonPath('data.doctors.0.name', $doctor->user->name)->assertJsonPath('data.laboratory.orders', 1)
            ->assertJsonPath('data.pharmacy.sales', 1)->assertJsonPath('data.pharmacy.sales_total', '105.00');
        $this->get('/reports/operational?from=2026-09-20&to=2026-09-20')->assertOk()->assertSee('Operational reports')->assertSee('₹500.00')->assertSee($doctor->department->name);
    }

    public function test_report_is_admin_only_branch_scoped_and_validates_bounded_dates(): void
    {
        $this->signIn('reception@lotus.test');
        $this->get('/reports/operational')->assertForbidden();
        $this->getJson('/api/v1/reports/operational')->assertForbidden();
        $this->signIn('admin@lotus.test');
        $otherBranch = $this->branch('CHN');
        Appointment::factory()->create(['hospital_id' => $otherBranch->hospital_id, 'branch_id' => $otherBranch->id, 'appointment_date' => '2026-09-20']);
        $this->getJson('/api/v1/reports/operational?from=2026-09-20&to=2026-09-20')->assertOk()->assertJsonPath('data.appointments.total', 0);
        $this->get('/reports/operational?from=invalid&to=2026-09-20')->assertSessionHasErrors('from');
        $this->get('/reports/operational?from=2026-09-21&to=2026-09-20')->assertSessionHasErrors('to');
        $this->get('/reports/operational?from=2025-01-01&to=2026-09-20')->assertSessionHasErrors('from');
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
