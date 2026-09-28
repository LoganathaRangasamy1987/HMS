<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\PatientMedicalHistory;
use App\Services\AuditService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PatientClinicalHistoryController extends Controller
{
    public function __construct(private TenantContext $tenant, private AuditService $audit) {}

    public function show(Request $request, string $patient): View|JsonResponse
    {
        abort_unless($this->tenant->can('PATIENT_HISTORY.VIEW'), 403);
        $patient = $this->patient($patient)->load([
            'allergies' => fn ($query) => $query->with(['recorder:id,name', 'branch:id,name'])->latest(),
            'medicalHistories' => fn ($query) => $query->with(['recorder:id,name', 'branch:id,name'])->latest(),
            'documents' => fn ($query) => $query->with(['uploader:id,name', 'branch:id,name'])->latest(),
        ]);

        return $request->expectsJson() ? response()->json(['data' => $patient]) : view('patients.clinical-history', compact('patient'));
    }

    public function storeAllergy(Request $request, string $patient): JsonResponse|RedirectResponse
    {
        abort_unless($this->tenant->can('PATIENT_HISTORY.MANAGE'), 403);
        $patient = $this->patient($patient);
        $validated = $request->validate($this->allergyRules());
        $allergy = DB::transaction(function () use ($request, $patient, $validated): PatientAllergy {
            $allergy = $patient->allergies()->create([...$validated, 'hospital_id' => $this->tenant->hospitalId(), 'branch_id' => $this->tenant->branchId(), 'recorded_by' => $request->user()->id]);
            $this->audit->record('patient_allergies', 'created', $allergy, null, $allergy->toArray());

            return $allergy;
        });

        return $request->expectsJson() ? response()->json(['data' => $allergy], 201) : back()->with('status', 'Allergy recorded.');
    }

    public function updateAllergy(Request $request, string $patient, string $allergy): JsonResponse|RedirectResponse
    {
        abort_unless($this->tenant->can('PATIENT_HISTORY.MANAGE'), 403);
        $patient = $this->patient($patient);
        $allergy = $patient->allergies()->where('hospital_id', $this->tenant->hospitalId())->findOrFail($allergy);
        $validated = $request->validate($this->allergyRules());
        DB::transaction(function () use ($allergy, $validated): void {
            $old = $allergy->toArray();
            $allergy->updateOrFail($validated);
            $this->audit->record('patient_allergies', 'updated', $allergy, $old, $allergy->fresh()->toArray());
        });

        return $request->expectsJson() ? response()->json(['data' => $allergy->fresh()]) : back()->with('status', 'Allergy updated.');
    }

    public function storeMedicalHistory(Request $request, string $patient): JsonResponse|RedirectResponse
    {
        abort_unless($this->tenant->can('PATIENT_HISTORY.MANAGE'), 403);
        $patient = $this->patient($patient);
        $validated = $this->validateMedicalHistory($request);
        $history = DB::transaction(function () use ($request, $patient, $validated): PatientMedicalHistory {
            $history = $patient->medicalHistories()->create([...$validated, 'hospital_id' => $this->tenant->hospitalId(), 'branch_id' => $this->tenant->branchId(), 'recorded_by' => $request->user()->id]);
            $this->audit->record('patient_medical_history', 'created', $history, null, $history->toArray());

            return $history;
        });

        return $request->expectsJson() ? response()->json(['data' => $history], 201) : back()->with('status', 'Medical history recorded.');
    }

    public function updateMedicalHistory(Request $request, string $patient, string $history): JsonResponse|RedirectResponse
    {
        abort_unless($this->tenant->can('PATIENT_HISTORY.MANAGE'), 403);
        $patient = $this->patient($patient);
        $history = $patient->medicalHistories()->where('hospital_id', $this->tenant->hospitalId())->findOrFail($history);
        $validated = $this->validateMedicalHistory($request);
        DB::transaction(function () use ($history, $validated): void {
            $old = $history->toArray();
            $history->updateOrFail($validated);
            $this->audit->record('patient_medical_history', 'updated', $history, $old, $history->fresh()->toArray());
        });

        return $request->expectsJson() ? response()->json(['data' => $history->fresh()]) : back()->with('status', 'Medical history updated.');
    }

    private function patient(string $id): Patient
    {
        return Patient::query()->forHospital($this->tenant->hospitalId())->findOrFail($id);
    }

    private function allergyRules(): array
    {
        return ['allergen' => ['required', 'string', 'max:150'], 'reaction' => ['nullable', 'string', 'max:500'], 'severity' => ['required', Rule::in(['unknown', 'mild', 'moderate', 'severe'])], 'status' => ['required', Rule::in(['active', 'inactive', 'entered_in_error'])], 'notes' => ['nullable', 'string', 'max:2000']];
    }

    private function validateMedicalHistory(Request $request): array
    {
        $validated = $request->validate(['condition' => ['required', 'string', 'max:200'], 'onset_date_unknown' => ['sometimes', 'boolean'], 'onset_date' => ['required_unless:onset_date_unknown,true', 'prohibited_if:onset_date_unknown,true', 'nullable', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Kolkata')->toDateString()], 'status' => ['required', Rule::in(['active', 'resolved', 'inactive', 'entered_in_error'])], 'notes' => ['nullable', 'string', 'max:4000']]);
        $validated['onset_date_unknown'] = (bool) ($validated['onset_date_unknown'] ?? false);
        if ($validated['onset_date_unknown']) {
            $validated['onset_date'] = null;
        }

        return $validated;
    }
}
