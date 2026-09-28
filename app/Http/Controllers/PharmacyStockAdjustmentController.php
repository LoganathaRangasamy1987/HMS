<?php

namespace App\Http\Controllers;

use App\Models\MedicineBatch;
use App\Models\PharmacyPurchaseItem;
use App\Models\PharmacySaleItem;
use App\Models\PharmacyStockAdjustment;
use App\Services\PharmacyStockAdjustmentService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PharmacyStockAdjustmentController extends Controller
{
    public function __construct(private TenantContext $tenant, private PharmacyStockAdjustmentService $adjustments) {}

    public function index(Request $request): View|JsonResponse
    {
        $this->view();
        $records = PharmacyStockAdjustment::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())
            ->with($this->adjustments->relations())->latest('requested_at')->paginate(20);
        $branch = fn ($query) => $query->where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId());
        $data = [
            'records' => $records,
            'batches' => MedicineBatch::where($branch)->with('medicine:id,code,name')->where('on_hand_quantity', '>', 0)->orderBy('expires_on')->get(),
            'saleItems' => PharmacySaleItem::whereHas('sale', $branch)->with(['sale:id,number', 'batch:id,batch_number'])->latest()->limit(200)->get(),
            'purchaseItems' => PharmacyPurchaseItem::whereHas('purchase', $branch)->with(['purchase:id,number', 'batch:id,batch_number'])->latest()->limit(200)->get(),
        ];

        return $request->expectsJson() ? response()->json(['data' => $records]) : view('pharmacy.adjustments.index', $data);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->manage();
        $record = $this->adjustments->request($request->validate([
            'type' => ['required', Rule::in(['SALE_RETURN', 'PURCHASE_RETURN', 'DAMAGED', 'EXPIRED'])],
            'medicine_batch_id' => ['required', 'integer'],
            'pharmacy_sale_item_id' => ['nullable', 'integer'],
            'pharmacy_purchase_item_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'decimal:0,3', 'gt:0'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'request_key' => ['required', 'uuid'],
        ]));

        return $request->expectsJson() ? response()->json(['data' => $record], $record->wasRecentlyCreated ? 201 : 200) : back()->with('status', 'Stock adjustment submitted for administrator approval.');
    }

    public function decide(Request $request, string $adjustment): JsonResponse|RedirectResponse
    {
        $this->approve();
        $data = $request->validate(['decision' => ['required', Rule::in(['APPROVED', 'REJECTED'])], 'decision_reason' => ['nullable', 'required_if:decision,REJECTED', 'string', 'min:3', 'max:500']]);
        $record = PharmacyStockAdjustment::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())->findOrFail($adjustment);
        $record = $this->adjustments->decide($record, $data['decision'], $data['decision_reason'] ?? null);

        return $request->expectsJson() ? response()->json(['data' => $record]) : back()->with('status', 'Stock adjustment decision recorded.');
    }

    private function view(): void
    {
        abort_unless($this->tenant->can('PHARMACY_ADJUSTMENT.VIEW'), 403);
    }

    private function manage(): void
    {
        abort_unless($this->tenant->can('PHARMACY_ADJUSTMENT.MANAGE'), 403);
    }

    private function approve(): void
    {
        abort_unless($this->tenant->can('PHARMACY_ADJUSTMENT.APPROVE'), 403);
    }
}
