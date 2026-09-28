<?php

namespace App\Http\Controllers;

use App\Models\Diagnosis;
use App\Models\Encounter;
use App\Services\AuditService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DiagnosisController extends Controller
{
    public function __construct(private TenantContext $tenant, private AuditService $audit) {}

    public function store(Request $request, string $encounter): JsonResponse|RedirectResponse
    {
        $encounter = $this->encounter($encounter, true);
        $data = $this->validated($request);
        $data['diagnosed_at'] = $this->localTime($data['diagnosed_at']);
        $diagnosis = Diagnosis::create([...$data, 'hospital_id' => $encounter->hospital_id, 'branch_id' => $encounter->branch_id, 'patient_id' => $encounter->patient_id, 'encounter_id' => $encounter->id, 'authored_by' => $request->user()->id]);
        $this->audit->record('diagnoses', 'recorded', $diagnosis, null, $diagnosis->toArray());

        return $request->expectsJson() ? response()->json(['data' => $diagnosis], 201) : back()->with('status', 'Diagnosis recorded.');
    }

    public function correct(Request $request, string $encounter, string $diagnosis): JsonResponse|RedirectResponse
    {
        $encounter = $this->encounter($encounter);
        $diagnosis = Diagnosis::query()->where('encounter_id', $encounter->id)->findOrFail($diagnosis);
        $data = $this->validated($request, true);
        unset($data['diagnosed_at']);
        $correction = $diagnosis->corrections()->create([...$data, 'corrected_by' => $request->user()->id, 'corrected_at' => now()]);
        $this->audit->record('diagnosis_corrections', 'created', $correction, null, $correction->toArray());

        return $request->expectsJson() ? response()->json(['data' => $correction], 201) : back()->with('status', 'Diagnosis correction recorded.');
    }

    private function encounter(string $id, bool $active = false): Encounter
    {
        abort_unless($this->tenant->can('ENCOUNTER.MANAGE'), 403);

        return Encounter::query()->where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())->when($active, fn ($query) => $query->where('status', 'ACTIVE'))->whereHas('doctorProfile', fn ($query) => $query->where('user_id', auth()->id()))->findOrFail($id);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $correction = false): array
    {
        $rules = ['type' => ['required', Rule::in(['PROVISIONAL', 'FINAL'])], 'description' => ['required', 'string', 'max:1000'], 'code_system' => ['nullable', 'string', 'max:50', 'required_with:code'], 'code' => ['nullable', 'string', 'max:50', 'required_with:code_system']];
        if ($correction) {
            $rules['reason'] = ['required', 'string', 'min:3', 'max:500'];
        } else {
            $rules['diagnosed_at'] = ['required', 'date_format:Y-m-d\\TH:i'];
        }

        return $request->validate($rules);
    }

    private function localTime(string $value): CarbonImmutable
    {
        $time = CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $value, $this->tenant->branch()->timezone);
        if ($time->isFuture()) {
            throw ValidationException::withMessages(['diagnosed_at' => 'Diagnosis time cannot be in the future.']);
        }

        return $time->utc();
    }
}
