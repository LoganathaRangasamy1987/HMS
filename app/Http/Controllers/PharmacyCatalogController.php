<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\MedicineType;
use App\Models\MedicineUnit;
use App\Models\PharmacyManufacturer;
use App\Models\PharmacySupplier;
use App\Services\AuditService;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PharmacyCatalogController extends Controller
{
    public function __construct(private TenantContext $tenant, private AuditService $audit) {}

    public function index(Request $request): View|JsonResponse
    {
        $this->view();
        $hospitalId = $this->tenant->hospitalId();
        $data = [
            'manufacturers' => PharmacyManufacturer::where('hospital_id', $hospitalId)->orderBy('name')->get(),
            'types' => MedicineType::where('hospital_id', $hospitalId)->orderBy('name')->get(),
            'units' => MedicineUnit::where('hospital_id', $hospitalId)->orderBy('name')->get(),
            'suppliers' => PharmacySupplier::where('hospital_id', $hospitalId)->orderBy('name')->get(),
            'medicines' => Medicine::where('hospital_id', $hospitalId)->with(['manufacturer', 'type', 'purchaseUnit', 'saleUnit'])->orderBy('name')->paginate(20),
        ];

        return $request->expectsJson() ? response()->json(['data' => $data]) : view('pharmacy.catalog', $data);
    }

    public function storeManufacturer(Request $request): JsonResponse|RedirectResponse
    {
        return $this->storeLookup($request, PharmacyManufacturer::class, 'pharmacy_manufacturers', ['name' => ['required', 'string', 'max:150']]);
    }

    public function storeType(Request $request): JsonResponse|RedirectResponse
    {
        return $this->storeLookup($request, MedicineType::class, 'medicine_types', ['name' => ['required', 'string', 'max:100']]);
    }

    public function storeUnit(Request $request): JsonResponse|RedirectResponse
    {
        return $this->storeLookup($request, MedicineUnit::class, 'medicine_units', ['name' => ['required', 'string', 'max:100'], 'symbol' => ['required', 'string', 'max:30']]);
    }

    public function storeSupplier(Request $request): JsonResponse|RedirectResponse
    {
        $this->manage();
        $data = $this->supplierData($request);
        $supplier = PharmacySupplier::create([...$data, 'hospital_id' => $this->tenant->hospitalId()]);
        $this->audit->record('pharmacy_suppliers', 'created', $supplier, null, $supplier->toArray());

        return $this->created($request, $supplier, 'Supplier created.');
    }

    public function updateSupplier(Request $request, string $supplier): JsonResponse|RedirectResponse
    {
        $this->manage();
        $supplier = PharmacySupplier::where('hospital_id', $this->tenant->hospitalId())->findOrFail($supplier);
        $old = $supplier->toArray();
        $supplier->updateOrFail($this->supplierData($request, $supplier));
        $this->audit->record('pharmacy_suppliers', 'updated', $supplier, $old, $supplier->fresh()->toArray());

        return $request->expectsJson() ? response()->json(['data' => $supplier->fresh()]) : back()->with('status', 'Supplier updated.');
    }

    private function storeLookup(Request $request, string $model, string $module, array $rules): JsonResponse|RedirectResponse
    {
        $this->manage();
        $rules['name'][] = Rule::unique($module)->where('hospital_id', $this->tenant->hospitalId());
        if (isset($rules['symbol'])) {
            $rules['symbol'][] = Rule::unique($module)->where('hospital_id', $this->tenant->hospitalId());
        }
        $data = $request->validate([...$rules, 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        foreach (array_keys($rules) as $field) {
            $data[$field] = trim((string) $data[$field]);
        }
        $record = $model::create([...$data, 'hospital_id' => $this->tenant->hospitalId(), 'status' => $data['status'] ?? 'active']);
        $this->audit->record($module, 'created', $record, null, $record->toArray());

        return $this->created($request, $record, 'Catalog value created.');
    }

    private function supplierData(Request $request, ?PharmacySupplier $supplier = null): array
    {
        if ($request->filled('code')) {
            $request->merge(['code' => strtoupper(trim($request->string('code')->toString()))]);
        }

        return $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/\A[A-Za-z0-9_-]+\z/D', Rule::unique('pharmacy_suppliers')->where('hospital_id', $this->tenant->hospitalId())->ignore($supplier?->id)],
            'name' => ['required', 'string', 'max:150'], 'contact_person' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:150'],
            'tax_registration_number' => ['nullable', 'string', 'max:50'], 'address' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function created(Request $request, Model $record, string $message): JsonResponse|RedirectResponse
    {
        return $request->expectsJson() ? response()->json(['data' => $record], 201) : back()->with('status', $message);
    }

    private function view(): void
    {
        abort_unless($this->tenant->can('PHARMACY_CATALOG.VIEW'), 403);
    }

    private function manage(): void
    {
        abort_unless($this->tenant->can('PHARMACY_CATALOG.MANAGE'), 403);
    }
}
