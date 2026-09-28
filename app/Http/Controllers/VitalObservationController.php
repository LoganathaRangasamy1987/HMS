<?php

namespace App\Http\Controllers;

use App\Models\Encounter;
use App\Models\VitalObservation;
use App\Services\AuditService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VitalObservationController extends Controller
{
    public function __construct(private TenantContext $tenant, private AuditService $audit) {}

    public function store(Request $request, string $encounter): JsonResponse|RedirectResponse
    {
        abort_unless($this->tenant->can('ENCOUNTER.MANAGE'), 403);
        $encounter = Encounter::query()->where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())->where('status', 'ACTIVE')->whereHas('doctorProfile', fn ($query) => $query->where('user_id', auth()->id()))->findOrFail($encounter);
        $data = $request->validate([
            'temperature' => ['nullable', 'decimal:0,2', 'between:20,50'], 'pulse' => ['nullable', 'integer', 'between:1,300'], 'respiratory_rate' => ['nullable', 'integer', 'between:1,100'],
            'systolic_bp' => ['nullable', 'integer', 'between:1,400'], 'diastolic_bp' => ['nullable', 'integer', 'between:1,300'], 'oxygen_saturation' => ['nullable', 'decimal:0,2', 'between:1,100'],
            'weight' => ['nullable', 'decimal:0,2', 'between:0.1,1000'], 'height' => ['nullable', 'decimal:0,2', 'between:1,300'], 'notes' => ['nullable', 'string', 'max:1000'], 'measured_at' => ['required', 'date_format:Y-m-d\\TH:i'],
        ]);
        if (! collect($data)->except(['notes', 'measured_at'])->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty()) {
            throw ValidationException::withMessages(['vitals' => 'Record at least one vital measurement.']);
        }
        $measuredAt = CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $data['measured_at'], $this->tenant->branch()->timezone);
        if ($measuredAt->isFuture()) {
            throw ValidationException::withMessages(['measured_at' => 'Measurement time cannot be in the future.']);
        }
        $data['measured_at'] = $measuredAt->utc();
        $vital = VitalObservation::create([...$data, 'hospital_id' => $encounter->hospital_id, 'branch_id' => $encounter->branch_id, 'patient_id' => $encounter->patient_id, 'encounter_id' => $encounter->id, 'recorded_by' => $request->user()->id]);
        $this->audit->record('vital_observations', 'recorded', $vital, null, $vital->toArray());
        $vital->refresh();

        return $request->expectsJson() ? response()->json(['data' => $vital], 201) : back()->with('status', 'Vitals recorded.');
    }
}
