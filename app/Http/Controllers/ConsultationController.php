<?php

namespace App\Http\Controllers;

use App\Models\Encounter;
use App\Models\Medicine;
use App\Services\ConsultationService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConsultationController extends Controller
{
    public function __construct(private TenantContext $tenant, private ConsultationService $consultations) {}

    public function show(Request $request, string $encounter): View|JsonResponse
    {
        $encounter = $this->encounter($encounter)->load(['patient.allergies', 'appointment', 'consultation.amendments.amendedBy:id,name', 'consultation.author:id,name', 'vitalObservations.recordedBy:id,name', 'diagnoses.author:id,name', 'diagnoses.corrections.correctedBy:id,name', 'prescription.items', 'prescription.prescriber:id,name']);
        $latest = $encounter->consultation?->amendments->last() ?? $encounter->consultation;
        $medicines = Medicine::where('hospital_id', $this->tenant->hospitalId())->where('status', 'active')->orderBy('name')->get();

        return $request->expectsJson() ? response()->json(['data' => $encounter, 'effective_consultation' => $latest]) : view('consultations.show', compact('encounter', 'latest', 'medicines'));
    }

    public function update(Request $request, string $encounter): RedirectResponse|JsonResponse
    {
        $consultation = $this->consultations->saveDraft($this->encounter($encounter), $request->user(), $this->validated($request));

        return $request->expectsJson() ? response()->json(['data' => $consultation]) : back()->with('status', 'Consultation draft saved.');
    }

    public function finalize(Request $request, string $encounter): RedirectResponse|JsonResponse
    {
        $consultation = $this->consultations->finalize($this->encounter($encounter), $request->user(), $this->validated($request));

        return $request->expectsJson() ? response()->json(['data' => $consultation]) : back()->with('status', 'Consultation finalized.');
    }

    public function amend(Request $request, string $encounter): RedirectResponse|JsonResponse
    {
        $encounter = $this->encounter($encounter)->load('consultation');
        abort_unless($encounter->consultation, 404);
        $data = $this->validated($request, true);
        $amendment = $this->consultations->amend($encounter->consultation, $request->user(), $data);

        return $request->expectsJson() ? response()->json(['data' => $amendment], 201) : back()->with('status', 'Consultation amendment recorded.');
    }

    private function encounter(string $id): Encounter
    {
        abort_unless($this->tenant->can('ENCOUNTER.MANAGE'), 403);

        return Encounter::query()->where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())
            ->whereHas('doctorProfile', fn ($query) => $query->where('user_id', auth()->id()))->findOrFail($id);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $reason = false): array
    {
        $rules = [
            'chief_complaint' => ['nullable', 'string', 'max:5000'],
            'history' => ['nullable', 'string', 'max:10000'],
            'examination' => ['nullable', 'string', 'max:10000'],
            'clinical_notes' => ['nullable', 'string', 'max:10000'],
            'follow_up_date' => ['nullable', 'date', 'after_or_equal:today'],
        ];
        if ($reason) {
            $rules['reason'] = ['required', 'string', 'min:3', 'max:500'];
        }

        return $request->validate($rules);
    }
}
