<?php

namespace App\Http\Controllers;

use App\Models\MedicineBatch;
use App\Models\PharmacySale;
use App\Models\PharmacySaleItem;
use App\Models\Prescription;
use App\Services\PharmacyDispenseService;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PharmacySaleController extends Controller
{
    public function __construct(private TenantContext $tenant, private PharmacyDispenseService $dispensing) {}

    public function index(Request $request): View|JsonResponse
    {
        $this->view();
        $sales = $this->scope()->with(['patient:id,uhid,first_name,last_name', 'invoice:id,number,status,total', 'dispensedBy:id,name'])->latest('dispensed_at')->paginate(20);
        $prescriptions = Prescription::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())
            ->whereHas('items', fn (Builder $query) => $query->whereNotNull('medicine_id')->whereDoesntHave('pharmacySaleItem'))
            ->with(['patient:id,uhid,first_name,last_name', 'prescriber:id,name'])->latest('prescribed_at')->limit(50)->get();

        return $request->expectsJson() ? response()->json(['data' => $sales]) : view('pharmacy.sales.index', compact('sales', 'prescriptions'));
    }

    public function create(Request $request): View
    {
        $this->manage();
        $data = $request->validate(['prescription_id' => ['required', 'integer']]);
        $prescription = Prescription::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())
            ->with(['patient', 'prescriber:id,name', 'items.medicine'])->findOrFail($data['prescription_id']);
        $dispensedIds =
            PharmacySaleItem::whereIn('prescription_item_id', $prescription->items->pluck('id'))->pluck('prescription_item_id');
        $prescription->setRelation('items', $prescription->items->whereNotIn('id', $dispensedIds)->whereNotNull('medicine_id')->values());
        $batches = MedicineBatch::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())
            ->whereIn('medicine_id', $prescription->items->pluck('medicine_id'))->where('status', 'active')
            ->whereDate('expires_on', '>', now($this->tenant->branch()->timezone)->toDateString())->where('on_hand_quantity', '>', 0)
            ->orderBy('expires_on')->get()->groupBy('medicine_id');

        return view('pharmacy.sales.create', compact('prescription', 'batches'));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->manage();
        $sale = $this->dispensing->dispense($request->validate([
            'prescription_id' => ['required', 'integer'],
            'request_key' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.prescription_item_id' => ['required', 'integer', 'distinct'],
            'items.*.medicine_batch_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:65535'],
        ]));

        return $request->expectsJson()
            ? response()->json(['data' => $sale], $sale->wasRecentlyCreated ? 201 : 200)
            : redirect()->route('pharmacy.sales.show', $sale)->with('status', 'Medicines dispensed and invoice issued.');
    }

    public function show(Request $request, string $sale): View|JsonResponse
    {
        $this->view();
        $sale = $this->scope()->with($this->dispensing->relations())->findOrFail($sale);

        return $request->expectsJson() ? response()->json(['data' => $sale]) : view('pharmacy.sales.show', compact('sale'));
    }

    private function scope(): Builder
    {
        return PharmacySale::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId());
    }

    private function view(): void
    {
        abort_unless($this->tenant->can('PHARMACY_SALE.VIEW'), 403);
    }

    private function manage(): void
    {
        abort_unless($this->tenant->can('PHARMACY_SALE.MANAGE'), 403);
    }
}
