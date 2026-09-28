<?php

namespace App\Services;

use App\Models\DoctorProfile;
use App\Models\Encounter;
use App\Models\Hospital;
use App\Models\Invoice;
use App\Models\LabOrder;
use App\Models\LabTest;
use App\Models\Patient;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LabOrderService
{
    public function __construct(
        private TenantContext $tenant,
        private InvoiceService $invoices,
        private PaymentService $payments,
        private AuditService $audit,
        private LabNotificationService $notifications,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data): LabOrder
    {
        return DB::transaction(function () use ($data): LabOrder {
            $patient = Patient::forHospital($this->tenant->hospitalId())->where('status', 'active')->findOrFail($data['patient_id']);
            $doctor = DoctorProfile::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())->where('status', 'active')->findOrFail($data['doctor_profile_id']);
            if ($this->tenant->membership()->role->name === 'DOCTOR' && $doctor->user_id !== auth()->id()) {
                throw ValidationException::withMessages(['doctor_profile_id' => 'Doctors can only place orders under their own profile.']);
            }
            $encounter = isset($data['encounter_id']) ? Encounter::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())->findOrFail($data['encounter_id']) : null;
            if ($encounter && ($encounter->patient_id !== $patient->id || $encounter->doctor_profile_id !== $doctor->id)) {
                throw ValidationException::withMessages(['encounter_id' => 'The encounter must belong to the selected patient and ordering doctor.']);
            }
            $tests = LabTest::where('hospital_id', $this->tenant->hospitalId())->where('status', 'active')->whereIn('id', $data['test_ids'])->with('activeVersion')->lockForUpdate()->get()->keyBy('id');
            if ($tests->count() !== count($data['test_ids']) || $tests->contains(fn (LabTest $test) => $test->activeVersion === null || $test->activeVersion->status !== 'active')) {
                throw ValidationException::withMessages(['test_ids' => 'Every selected test must have an active definition.']);
            }
            $totalCents = $tests->sum(fn (LabTest $test): int => $this->cents($test->activeVersion->price));
            $invoice = Invoice::create(['hospital_id' => $this->tenant->hospitalId(), 'branch_id' => $this->tenant->branchId(), 'patient_id' => $patient->id, 'appointment_id' => null, 'created_by' => auth()->id()]);
            $invoice->subtotal = $invoice->total = $this->money($totalCents);
            $invoice->saveOrFail();
            $year = (int) now('Asia/Kolkata')->format('Y');
            Hospital::whereKey($this->tenant->hospitalId())->lockForUpdate()->firstOrFail();
            $sequence = DB::table('lab_order_number_sequences')->where('hospital_id', $this->tenant->hospitalId())->where('year', $year)->first();
            $next = ($sequence?->last_number ?? 0) + 1;
            DB::table('lab_order_number_sequences')->updateOrInsert(['hospital_id' => $this->tenant->hospitalId(), 'year' => $year], ['last_number' => $next]);
            $order = LabOrder::create([
                'hospital_id' => $this->tenant->hospitalId(), 'branch_id' => $this->tenant->branchId(), 'patient_id' => $patient->id,
                'encounter_id' => $encounter?->id, 'doctor_profile_id' => $doctor->id, 'invoice_id' => $invoice->id,
                'number' => sprintf('LAB-%d-%d-%06d', $this->tenant->hospitalId(), $year, $next), 'status' => 'ORDERED',
                'clinical_notes' => $data['clinical_notes'] ?? null, 'ordered_by' => auth()->id(), 'ordered_at' => now(),
            ]);
            foreach ($data['test_ids'] as $testId) {
                $test = $tests->get($testId);
                $version = $test->activeVersion;
                $line = $invoice->lines()->create([
                    'service_item_id' => null, 'service_code' => $test->code, 'description' => $test->name, 'quantity' => 1,
                    'unit_price' => $version->price, 'discount_type' => 'NONE', 'discount_value' => '0.00', 'tax_rate_percent' => '0.00',
                    'subtotal' => $version->price, 'discount_amount' => '0.00', 'tax_amount' => '0.00', 'total' => $version->price,
                ]);
                $order->items()->create([
                    'lab_test_id' => $test->id, 'lab_test_version_id' => $version->id, 'invoice_line_id' => $line->id,
                    'test_code' => $test->code, 'test_name' => $test->name, 'version' => $version->version,
                    'currency' => $version->currency, 'price' => $version->price, 'status' => 'ORDERED',
                ]);
            }
            $this->invoices->issue($invoice);
            $this->audit->record('lab_orders', 'created', $order, null, $order->toArray());
            $this->notifications->orderCreated($order);

            return $order->load($this->relations());
        }, 3);
    }

    public function cancel(LabOrder $order, string $reason): LabOrder
    {
        return DB::transaction(function () use ($order, $reason): LabOrder {
            $order = LabOrder::whereKey($order->id)->where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())->lockForUpdate()->firstOrFail();
            if ($this->tenant->membership()->role->name === 'DOCTOR' && $order->doctorProfile()->where('user_id', auth()->id())->doesntExist()) {
                abort(404);
            }
            if ($order->status !== 'ORDERED') {
                throw ValidationException::withMessages(['order' => 'Only an order without specimen activity can be cancelled.']);
            }
            $invoice = Invoice::whereKey($order->invoice_id)->lockForUpdate()->firstOrFail();
            if ($this->payments->summary($invoice)['net_paid'] !== '0.00') {
                throw ValidationException::withMessages(['order' => 'Refund all collected payment before cancelling this laboratory order.']);
            }
            $old = $order->toArray();
            $order->update(['status' => 'CANCELLED', 'cancelled_by' => auth()->id(), 'cancelled_at' => now(), 'cancellation_reason' => $reason]);
            $order->items()->update(['status' => 'CANCELLED']);
            $invoice->status = 'VOID';
            $invoice->saveOrFail();
            $this->audit->record('lab_orders', 'cancelled', $order, $old, $order->fresh()->toArray());
            $this->audit->record('invoices', 'voided_for_lab_cancellation', $invoice, null, $invoice->toArray());

            return $order->load($this->relations());
        }, 3);
    }

    /** @return array<int, string> */
    public function relations(): array
    {
        return ['patient:id,uhid,first_name,last_name', 'doctorProfile.user:id,name', 'encounter:id,status', 'items.versionDefinition.sampleType', 'items.specimens.events.recordedBy:id,name', 'items.specimens.results.enteredBy:id,name', 'items.specimens.results.verifiedBy:id,name', 'items.specimens.collectedBy:id,name', 'items.specimens.receivedBy:id,name', 'items.specimens.processingBy:id,name', 'items.specimens.rejectedBy:id,name', 'invoice.lines', 'invoice.payments', 'invoice.financialAdjustments'];
    }

    private function cents(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function money(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
