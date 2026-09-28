<?php

namespace App\Http\Controllers;

use App\Models\Encounter;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Services\AuditService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PrescriptionController extends Controller
{
    public function __construct(private TenantContext $tenant, private AuditService $audit) {}

    public function store(Request $request, string $encounter): JsonResponse|RedirectResponse
    {
        $encounter = $this->encounter($encounter, true);
        $request->merge(['items' => collect($request->input('items', []))->filter(fn ($item) => filled($item['medicine_id'] ?? null))->values()->all()]);
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'], 'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.medicine_id' => ['required', 'integer', Rule::exists('medicines', 'id')->where(fn ($query) => $query->where('hospital_id', $this->tenant->hospitalId())->where('status', 'active'))],
            'items.*.dose' => ['required', 'string', 'max:100'], 'items.*.frequency' => ['required', 'string', 'max:100'],
            'items.*.duration' => ['required', 'string', 'max:100'], 'items.*.route' => ['required', Rule::in(['Oral', 'Topical', 'Inhaled', 'Injection', 'Other'])],
            'items.*.timing' => ['nullable', 'string', 'max:100'], 'items.*.advice' => ['nullable', 'string', 'max:500'],
        ]);
        $prescription = DB::transaction(function () use ($encounter, $data, $request): Prescription {
            $encounter = Encounter::query()->lockForUpdate()->findOrFail($encounter->id);
            if ($encounter->status !== 'ACTIVE' || $encounter->prescription()->exists()) {
                throw ValidationException::withMessages(['prescription' => 'This encounter cannot accept another prescription.']);
            }
            $prescription = Prescription::create(['hospital_id' => $encounter->hospital_id, 'branch_id' => $encounter->branch_id, 'patient_id' => $encounter->patient_id, 'encounter_id' => $encounter->id, 'notes' => $data['notes'] ?? null, 'prescribed_at' => now(), 'prescribed_by' => $request->user()->id]);
            $medicines = Medicine::where('hospital_id', $encounter->hospital_id)->whereIn('id', collect($data['items'])->pluck('medicine_id'))->get()->keyBy('id');
            foreach ($data['items'] as $item) {
                $medicine = $medicines->get($item['medicine_id']);
                $prescription->items()->create([...$item, 'medicine_name' => $medicine->name, 'strength' => $medicine->strength]);
            }
            $this->audit->record('prescriptions', 'created', $prescription, null, [...$prescription->toArray(), 'item_count' => count($data['items'])]);

            return $prescription->load('items');
        });

        return $request->expectsJson() ? response()->json(['data' => $prescription], 201) : back()->with('status', 'Prescription saved.');
    }

    public function print(string $encounter): View
    {
        $encounter = $this->encounter($encounter)->load(['patient.allergies', 'doctorProfile.user', 'hospital', 'branch', 'prescription.items', 'prescription.prescriber']);
        abort_unless($encounter->prescription, 404);

        return view('prescriptions.print', compact('encounter'));
    }

    private function encounter(string $id, bool $active = false): Encounter
    {
        abort_unless($this->tenant->can('ENCOUNTER.MANAGE'), 403);

        return Encounter::query()->where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())
            ->when($active, fn ($query) => $query->where('status', 'ACTIVE'))
            ->whereHas('doctorProfile', fn ($query) => $query->where('user_id', auth()->id()))->findOrFail($id);
    }
}
