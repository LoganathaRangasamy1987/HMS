<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Services\AuditService;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MedicineController extends Controller
{
    public function __construct(private TenantContext $tenant, private AuditService $audit) {}

    public function index(Request $request): View|JsonResponse
    {
        abort_unless($this->tenant->can('MEDICINE.VIEW'), 403);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $medicines = $this->medicines()
            ->with(['manufacturer', 'type', 'purchaseUnit', 'saleUnit'])
            ->when(! $this->tenant->can('MEDICINE.MANAGE'), fn (Builder $query) => $query->where('status', 'active'))
            ->when($filters['q'] ?? null, fn (Builder $query, string $q) => $query->where(fn (Builder $search) => $search->where('code', 'like', '%'.$q.'%')->orWhere('name', 'like', '%'.$q.'%')->orWhere('generic_name', 'like', '%'.$q.'%')->orWhereHas('manufacturer', fn (Builder $manufacturer) => $manufacturer->where('name', 'like', '%'.$q.'%'))))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderBy('name')->paginate(20)->withQueryString();

        return $request->expectsJson() ? response()->json($medicines) : view('medicines.index', compact('medicines'));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->manage();
        $medicine = Medicine::create([...$this->validated($request), 'hospital_id' => $this->tenant->hospitalId()]);
        $this->audit->record('medicines', 'created', $medicine, null, $medicine->toArray());

        return $request->expectsJson() ? response()->json(['data' => $medicine], 201) : back()->with('status', 'Medicine created.');
    }

    public function update(Request $request, string $medicine): JsonResponse|RedirectResponse
    {
        $this->manage();
        $medicine = $this->medicines()->findOrFail($medicine);
        $old = $medicine->toArray();
        $medicine->updateOrFail($this->validated($request, $medicine));
        $this->audit->record('medicines', 'updated', $medicine, $old, $medicine->fresh()->toArray());

        return $request->expectsJson() ? response()->json(['data' => $medicine->fresh()]) : back()->with('status', 'Medicine updated.');
    }

    private function medicines(): Builder
    {
        return Medicine::where('hospital_id', $this->tenant->hospitalId());
    }

    private function manage(): void
    {
        abort_unless($this->tenant->can('MEDICINE.MANAGE'), 403);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Medicine $medicine = null): array
    {
        if ($request->filled('code')) {
            $request->merge(['code' => strtoupper(trim($request->string('code')->toString()))]);
        }

        return $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/\A[A-Za-z0-9_-]+\z/D', Rule::unique('medicines')->where('hospital_id', $this->tenant->hospitalId())->ignore($medicine?->id)],
            'name' => ['required', 'string', 'max:150'], 'generic_name' => ['nullable', 'string', 'max:150'],
            'pharmacy_manufacturer_id' => ['nullable', 'integer', Rule::exists('pharmacy_manufacturers', 'id')->where(fn ($query) => $query->where('hospital_id', $this->tenant->hospitalId())->where('status', 'active'))],
            'medicine_type_id' => ['nullable', 'integer', Rule::exists('medicine_types', 'id')->where(fn ($query) => $query->where('hospital_id', $this->tenant->hospitalId())->where('status', 'active'))],
            'form' => ['required', 'string', 'max:50'], 'strength' => ['required', 'string', 'max:100'],
            'purchase_unit_id' => ['nullable', 'integer', Rule::exists('medicine_units', 'id')->where(fn ($query) => $query->where('hospital_id', $this->tenant->hospitalId())->where('status', 'active'))],
            'sale_unit_id' => ['nullable', 'integer', Rule::exists('medicine_units', 'id')->where(fn ($query) => $query->where('hospital_id', $this->tenant->hospitalId())->where('status', 'active'))],
            'reorder_level' => ['nullable', 'decimal:0,3', 'min:0', 'max:999999999.999'],
            'tax_rate_percent' => ['nullable', 'decimal:0,2', 'between:0,100'],
            'prescription_required' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }
}
