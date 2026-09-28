<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class Patient360Controller extends Controller
{
    public function __construct(private TenantContext $tenant) {}

    public function __invoke(Request $request, string $patient): View|JsonResponse
    {
        abort_unless($this->tenant->can('PATIENT.VIEW'), 403);
        $filters = $request->validate([
            'section' => ['nullable', Rule::in(['appointments', 'clinical', 'billing'])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:25'],
        ]);
        $patient = Patient::query()->forHospital($this->tenant->hospitalId())->findOrFail($patient);
        $canClinical = $this->tenant->can('PATIENT_HISTORY.VIEW');
        $canBilling = $this->tenant->can('INVOICE.MANAGE');
        $section = $filters['section'] ?? null;
        abort_if($section === 'clinical' && ! $canClinical, 403);
        abort_if($section === 'billing' && ! $canBilling, 403);

        $timeline = $this->timeline($patient, $section, $canClinical, $canBilling, (int) ($filters['per_page'] ?? 10));
        $summary = $this->summary($patient, $canClinical, $canBilling);

        return $request->expectsJson()
            ? response()->json(['data' => $patient->only(['id', 'uhid', 'first_name', 'last_name', 'date_of_birth', 'date_of_birth_unknown', 'gender', 'blood_group', 'mobile', 'status']), 'summary' => $summary, 'permissions' => compact('canClinical', 'canBilling'), 'timeline' => $timeline])
            : view('patients.timeline', compact('patient', 'summary', 'timeline', 'canClinical', 'canBilling', 'section'));
    }

    /** @return array<string, int|string|null> */
    private function summary(Patient $patient, bool $canClinical, bool $canBilling): array
    {
        $branchId = $this->tenant->branchId();
        $summary = [
            'appointments' => DB::table('appointments')->where('patient_id', $patient->id)->where('branch_id', $branchId)->count(),
            'last_appointment' => DB::table('appointments')->where('patient_id', $patient->id)->where('branch_id', $branchId)->max('appointment_date'),
        ];
        if ($canClinical) {
            $encounters = DB::table('encounters')->join('doctor_profiles', 'doctor_profiles.id', '=', 'encounters.doctor_profile_id')
                ->where('encounters.patient_id', $patient->id)->where('encounters.branch_id', $branchId)->where('doctor_profiles.user_id', auth()->id());
            $summary['encounters'] = (clone $encounters)->count();
            $summary['active_allergies'] = DB::table('patient_allergies')->where('patient_id', $patient->id)->where('status', 'active')->count();
            $summary['diagnoses'] = DB::table('diagnoses')->join('encounters', 'encounters.id', '=', 'diagnoses.encounter_id')->join('doctor_profiles', 'doctor_profiles.id', '=', 'encounters.doctor_profile_id')
                ->where('diagnoses.patient_id', $patient->id)->where('encounters.branch_id', $branchId)->where('doctor_profiles.user_id', auth()->id())->count();
        }
        if ($canBilling) {
            $invoices = DB::table('invoices')->where('patient_id', $patient->id)->where('branch_id', $branchId);
            $summary['invoices'] = (clone $invoices)->count();
            $summary['outstanding'] = (string) (clone $invoices)->whereIn('status', ['ISSUED', 'PARTIALLY_PAID'])->sum('total');
        }

        return $summary;
    }

    private function timeline(Patient $patient, ?string $section, bool $canClinical, bool $canBilling, int $perPage): LengthAwarePaginator
    {
        $sources = [];
        if ($section === null || $section === 'appointments') {
            $sources[] = DB::table('appointments')->where('patient_id', $patient->id)->where('branch_id', $this->tenant->branchId())
                ->selectRaw("created_at as event_at, 'appointment' as event_type, id as record_id, status as title, reason as detail");
        }
        if ($canClinical && ($section === null || $section === 'clinical')) {
            $sources = [...$sources, ...$this->clinicalSources($patient)];
        }
        if ($canBilling && ($section === null || $section === 'billing')) {
            $sources = [...$sources, ...$this->billingSources($patient)];
        }
        $union = array_shift($sources);
        foreach ($sources as $source) {
            $union->unionAll($source);
        }
        $timeline = DB::query()->fromSub($union, 'patient_timeline')->orderByDesc('event_at')->orderByDesc('record_id')->paginate($perPage)->withQueryString();
        $timeline->getCollection()->transform(function (object $event) use ($patient): object {
            $event->display_at = CarbonImmutable::parse($event->event_at)->timezone($this->tenant->branch()->timezone)->format('d M Y H:i');
            $event->url = match ($event->event_type) {
                'encounter', 'diagnosis', 'prescription' => route('consultations.show', $event->record_id),
                'allergy' => route('patients.clinical-history', $patient),
                'invoice', 'payment' => route('invoices.show', $event->record_id),
                default => route('appointments.index'),
            };

            return $event;
        });

        return $timeline;
    }

    /** @return list<Builder> */
    private function clinicalSources(Patient $patient): array
    {
        $encounters = fn () => DB::table('encounters')->join('doctor_profiles', 'doctor_profiles.id', '=', 'encounters.doctor_profile_id')
            ->where('encounters.patient_id', $patient->id)->where('encounters.branch_id', $this->tenant->branchId())->where('doctor_profiles.user_id', auth()->id());

        return [
            $encounters()->selectRaw("encounters.opened_at as event_at, 'encounter' as event_type, encounters.id as record_id, encounters.status as title, encounters.encounter_type as detail"),
            DB::table('diagnoses')->join('encounters', 'encounters.id', '=', 'diagnoses.encounter_id')->join('doctor_profiles', 'doctor_profiles.id', '=', 'encounters.doctor_profile_id')
                ->where('diagnoses.patient_id', $patient->id)->where('encounters.branch_id', $this->tenant->branchId())->where('doctor_profiles.user_id', auth()->id())
                ->selectRaw("diagnoses.diagnosed_at as event_at, 'diagnosis' as event_type, encounters.id as record_id, diagnoses.type as title, diagnoses.description as detail"),
            DB::table('prescriptions')->join('encounters', 'encounters.id', '=', 'prescriptions.encounter_id')->join('doctor_profiles', 'doctor_profiles.id', '=', 'encounters.doctor_profile_id')
                ->where('prescriptions.patient_id', $patient->id)->where('encounters.branch_id', $this->tenant->branchId())->where('doctor_profiles.user_id', auth()->id())
                ->selectRaw("prescriptions.prescribed_at as event_at, 'prescription' as event_type, encounters.id as record_id, 'Saved prescription' as title, prescriptions.notes as detail"),
            DB::table('patient_allergies')->where('patient_id', $patient->id)->where('hospital_id', $this->tenant->hospitalId())
                ->selectRaw("created_at as event_at, 'allergy' as event_type, id as record_id, status as title, allergen as detail"),
        ];
    }

    /** @return list<Builder> */
    private function billingSources(Patient $patient): array
    {
        return [
            DB::table('invoices')->where('patient_id', $patient->id)->where('branch_id', $this->tenant->branchId())
                ->selectRaw("COALESCE(issued_at, created_at) as event_at, 'invoice' as event_type, id as record_id, status as title, number as detail"),
            DB::table('payments')->join('invoices', 'invoices.id', '=', 'payments.invoice_id')->where('invoices.patient_id', $patient->id)->where('invoices.branch_id', $this->tenant->branchId())
                ->selectRaw("payments.received_at as event_at, 'payment' as event_type, invoices.id as record_id, payments.mode as title, CAST(payments.amount AS CHAR) as detail"),
        ];
    }
}
