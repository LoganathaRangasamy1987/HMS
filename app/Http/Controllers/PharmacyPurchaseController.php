<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\PharmacyPurchase;
use App\Models\PharmacySupplier;
use App\Services\PharmacyReceiptService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PharmacyPurchaseController extends Controller
{
    public function __construct(private TenantContext $tenant, private PharmacyReceiptService $receipts) {}

    public function index(Request $request): View|JsonResponse
    {
        $this->view();
        $purchases = PharmacyPurchase::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())
            ->with(['supplier', 'receivedBy:id,name'])->latest('received_at')->paginate(20);
        $batches = MedicineBatch::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())
            ->with('medicine:id,code,name')->orderBy('expires_on')->get();
        $data = [
            'purchases' => $purchases,
            'batches' => $batches,
            'suppliers' => PharmacySupplier::where('hospital_id', $this->tenant->hospitalId())->where('status', 'active')->orderBy('name')->get(),
            'medicines' => Medicine::where('hospital_id', $this->tenant->hospitalId())->where('status', 'active')->orderBy('name')->get(),
        ];

        return $request->expectsJson() ? response()->json(['data' => ['purchases' => $purchases, 'batches' => $batches]]) : view('pharmacy.purchases', $data);
    }

    public function show(Request $request, string $purchase): View|JsonResponse
    {
        $this->view();
        $purchase = PharmacyPurchase::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())
            ->with($this->receipts->relations())->findOrFail($purchase);

        return $request->expectsJson() ? response()->json(['data' => $purchase]) : view('pharmacy.purchase', compact('purchase'));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->manage();
        $hospitalId = $this->tenant->hospitalId();
        $data = $request->validate([
            'pharmacy_supplier_id' => ['required', 'integer', Rule::exists('pharmacy_suppliers', 'id')->where(fn ($query) => $query->where('hospital_id', $hospitalId)->where('status', 'active'))],
            'supplier_invoice_number' => ['required', 'string', 'max:100'],
            'purchase_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'request_key' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.medicine_id' => ['required', 'integer'],
            'items.*.batch_number' => ['required', 'string', 'max:100'],
            'items.*.manufactured_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:purchase_date'],
            'items.*.expires_on' => ['required', 'date_format:Y-m-d', 'after:purchase_date'],
            'items.*.quantity' => ['required', 'decimal:0,3', 'gt:0'],
            'items.*.free_quantity' => ['nullable', 'decimal:0,3', 'min:0'],
            'items.*.unit_cost' => ['required', 'decimal:0,2', 'gt:0'],
            'items.*.sale_price' => ['required', 'decimal:0,2', 'gt:0'],
            'items.*.tax_rate_percent' => ['required', 'decimal:0,2', 'between:0,100'],
        ]);
        $purchase = $this->receipts->receive($data);

        return $request->expectsJson()
            ? response()->json(['data' => $purchase], $purchase->wasRecentlyCreated ? 201 : 200)
            : redirect()->route('pharmacy.purchases.show', $purchase)->with('status', 'Supplier receipt posted.');
    }

    private function view(): void
    {
        abort_unless($this->tenant->can('PHARMACY_PURCHASE.VIEW'), 403);
    }

    private function manage(): void
    {
        abort_unless($this->tenant->can('PHARMACY_PURCHASE.MANAGE'), 403);
    }
}
