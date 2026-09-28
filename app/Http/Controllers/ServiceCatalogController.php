<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\ServiceItem;
use App\Services\AuditService;
use App\Services\ServicePricing;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ServiceCatalogController extends Controller
{
    public function __construct(private TenantContext $tenant, private AuditService $audit, private ServicePricing $pricing) {}

    public function index(Request $request): View|JsonResponse
    {
        abort_unless($this->tenant->can('SERVICE.VIEW'), 403);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'type' => ['nullable', Rule::in(['CONSULTATION', 'SERVICE'])], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $services = $this->services()
            ->when(! $this->tenant->isAdmin(), fn (Builder $query) => $query->where('status', 'active')->whereDoesntHave('branchPrices', fn (Builder $prices) => $prices->where('branch_id', $this->tenant->branchId())->where('is_available', false)))
            ->when($filters['q'] ?? null, fn (Builder $query, string $q) => $query->where(fn (Builder $search) => $search->where('code', 'like', '%'.$q.'%')->orWhere('name', 'like', '%'.$q.'%')))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->with(['branchPrices' => fn ($query) => $query->where('branch_id', $this->tenant->branchId())])
            ->orderBy('name')->paginate(15)->withQueryString();

        return $request->expectsJson() ? response()->json($services) : view('services.index', compact('services'));
    }

    public function create(): View
    {
        $this->manage();

        return view('services.form', $this->formData(new ServiceItem(['currency' => 'INR', 'status' => 'active', 'discount_type' => 'none', 'discount_value' => '0.00', 'tax_rate_percent' => '0.00'])));
    }

    public function edit(string $service): View|JsonResponse
    {
        $this->manage();
        $item = $this->services()->with('branchPrices')->findOrFail($service);

        return request()->expectsJson() ? response()->json(['data' => $item]) : view('services.form', $this->formData($item));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->manage();
        $data = $this->validated($request);
        $item = DB::transaction(function () use ($data): ServiceItem {
            $item = ServiceItem::create([...$data, 'hospital_id' => $this->tenant->hospitalId(), 'currency' => 'INR']);
            $this->audit->record('service_items', 'created', $item, null, $item->toArray());

            return $item;
        });

        return $request->expectsJson() ? response()->json(['data' => $item], 201) : redirect()->route('services.edit', $item)->with('status', 'Service created.');
    }

    public function update(Request $request, string $service): JsonResponse|RedirectResponse
    {
        $this->manage();
        $item = $this->services()->findOrFail($service);
        $data = $this->validated($request, $item);
        DB::transaction(function () use ($item, $data): void {
            $old = $item->toArray();
            $item->updateOrFail($data);
            $this->audit->record('service_items', 'updated', $item, $old, $item->fresh()->toArray());
        });

        return $request->expectsJson() ? response()->json(['data' => $item->fresh()]) : back()->with('status', 'Service updated.');
    }

    public function updateBranchPrice(Request $request, string $service): JsonResponse|RedirectResponse
    {
        $this->manage();
        $item = $this->services()->findOrFail($service);
        $data = $request->validate([
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('hospital_id', $this->tenant->hospitalId())],
            'base_price' => ['nullable', 'decimal:0,2', 'between:0,9999999999.99'],
            'tax_rate_percent' => ['nullable', 'decimal:0,2', 'between:0,100'],
            'discount_type' => ['nullable', Rule::in(['none', 'percentage', 'fixed'])],
            'discount_value' => ['nullable', 'decimal:0,2', 'between:0,9999999999.99'],
            'is_available' => ['required', 'boolean'],
        ]);
        $this->validateDiscount($data, $item);
        $price = DB::transaction(function () use ($item, $data) {
            $existing = $item->branchPrices()->where('branch_id', $data['branch_id'])->first();
            $old = $existing?->toArray();
            $price = $existing ?? $item->branchPrices()->make(['branch_id' => $data['branch_id']]);
            $price->fill($data)->saveOrFail();
            $this->audit->record('service_branch_prices', $existing ? 'updated' : 'created', $price, $old, $price->toArray());

            return $price;
        });

        return $request->expectsJson() ? response()->json(['data' => $price], $price->wasRecentlyCreated ? 201 : 200) : back()->with('status', 'Branch price saved.');
    }

    public function quote(Request $request, string $service): JsonResponse|View
    {
        abort_unless($this->tenant->can('SERVICE.VIEW'), 403);
        $data = $request->validate(['branch_id' => ['nullable', 'integer']]);
        $branchId = $this->tenant->isAdmin() ? ($data['branch_id'] ?? $this->tenant->branchId()) : $this->tenant->branchId();
        $branch = Branch::where('hospital_id', $this->tenant->hospitalId())->findOrFail($branchId);
        $item = $this->services()->findOrFail($service);

        $quote = $this->pricing->quote($item, $branch);

        return $request->expectsJson() ? response()->json(['data' => $quote]) : view('services.quote', compact('item', 'branch', 'quote'));
    }

    private function services(): Builder
    {
        return ServiceItem::where('hospital_id', $this->tenant->hospitalId());
    }

    private function manage(): void
    {
        abort_unless($this->tenant->can('SERVICE.MANAGE'), 403);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?ServiceItem $item = null): array
    {
        if ($request->filled('code')) {
            $request->merge(['code' => strtoupper(trim($request->input('code')))]);
        }
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/\A[A-Za-z0-9_-]+\z/D', Rule::unique('service_items')->where('hospital_id', $this->tenant->hospitalId())->ignore($item?->id)],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(['CONSULTATION', 'SERVICE'])],
            'description' => ['nullable', 'string', 'max:1000'],
            'base_price' => ['required', 'decimal:0,2', 'between:0,9999999999.99'],
            'tax_rate_percent' => ['required', 'decimal:0,2', 'between:0,100'],
            'discount_type' => ['required', Rule::in(['none', 'percentage', 'fixed'])],
            'discount_value' => ['required', 'decimal:0,2', 'between:0,9999999999.99'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        $this->validateDiscount($data);

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function validateDiscount(array $data, ?ServiceItem $item = null): void
    {
        if ($item && (isset($data['discount_type']) xor isset($data['discount_value']))) {
            throw ValidationException::withMessages(['discount_value' => 'Enter both discount type and value, or leave both blank to inherit.']);
        }
        $type = $data['discount_type'] ?? $item?->discount_type ?? 'none';
        $value = $this->hundredths($data['discount_value'] ?? $item?->discount_value ?? '0');
        $price = $this->hundredths($data['base_price'] ?? $item?->base_price ?? '0');
        if (($type === 'none' && $value !== 0) || ($type === 'percentage' && $value > 10000) || ($type === 'fixed' && $value > $price)) {
            throw ValidationException::withMessages(['discount_value' => 'Discount must be zero for none, at most 100% for percentage, or no more than the base price for fixed.']);
        }
    }

    private function hundredths(string|int $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', (string) $value, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    /** @return array<string, mixed> */
    private function formData(ServiceItem $item): array
    {
        return ['item' => $item, 'branches' => Branch::where('hospital_id', $this->tenant->hospitalId())->orderBy('name')->get()];
    }
}
