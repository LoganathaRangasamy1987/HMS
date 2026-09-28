<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\PharmacyPurchase;
use App\Models\PharmacySale;
use App\Models\PharmacyStockAdjustment;
use App\Models\Prescription;
use App\Services\AuditService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PharmacyWorkspaceController extends Controller
{
    public function __construct(private TenantContext $tenant, private AuditService $audit) {}

    public function index(Request $request): View|JsonResponse
    {
        $this->view();
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'stock_status' => ['nullable', Rule::in(['in_stock', 'low_stock', 'out_of_stock'])]]);
        $branch = $this->tenant->branch();
        $today = CarbonImmutable::today($branch->timezone);
        $warningDate = $today->addDays($branch->pharmacy_expiry_warning_days);
        $medicineQuery = Medicine::where('hospital_id', $this->tenant->hospitalId())->where('status', 'active')
            ->withSum(['batches as branch_on_hand' => fn (Builder $query) => $query->where('branch_id', $branch->id)], 'on_hand_quantity')
            ->withCount(['batches as active_batch_count' => fn (Builder $query) => $query->where('branch_id', $branch->id)->where('on_hand_quantity', '>', 0)])
            ->when($filters['q'] ?? null, function (Builder $query, string $search) use ($branch): void {
                $term = '%'.trim($search).'%';
                $query->where(fn (Builder $query) => $query->where('code', 'like', $term)->orWhere('name', 'like', $term)->orWhere('generic_name', 'like', $term)
                    ->orWhereHas('batches', fn (Builder $query) => $query->where('branch_id', $branch->id)->where('batch_number', 'like', $term)));
            })->orderBy('name');
        $medicines = $medicineQuery->get()->map(function (Medicine $medicine): Medicine {
            $onHand = (float) ($medicine->branch_on_hand ?? 0);
            $reorder = (float) $medicine->reorder_level;
            $medicine->stock_status = $onHand <= 0 ? 'out_of_stock' : ($onHand <= $reorder ? 'low_stock' : 'in_stock');

            return $medicine;
        })->when($filters['stock_status'] ?? null, fn ($items, string $status) => $items->where('stock_status', $status))->values();
        $page = max(1, $request->integer('page', 1));
        $stock = new LengthAwarePaginator($medicines->forPage($page, 20)->values(), $medicines->count(), 20, $page, ['path' => $request->url(), 'query' => $request->query()]);
        $alertBatches = MedicineBatch::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $branch->id)->where('on_hand_quantity', '>', 0)
            ->whereDate('expires_on', '<=', $warningDate->toDateString())->with('medicine:id,code,name')->orderBy('expires_on')->get();
        $start = $today->utc();
        $end = $today->addDay()->utc();
        $summary = [
            'medicine_count' => $medicines->count(),
            'low_stock_count' => $medicines->where('stock_status', 'low_stock')->count(),
            'out_of_stock_count' => $medicines->where('stock_status', 'out_of_stock')->count(),
            'expired_batch_count' => $alertBatches->where('expires_on', '<=', $today)->count(),
            'expiring_batch_count' => $alertBatches->where('expires_on', '>', $today)->count(),
            'pending_prescriptions' => Prescription::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $branch->id)->whereHas('items', fn (Builder $query) => $query->whereNotNull('medicine_id')->whereDoesntHave('pharmacySaleItem'))->count(),
            'pending_adjustments' => PharmacyStockAdjustment::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $branch->id)->where('status', 'PENDING')->count(),
            'receipts_today' => PharmacyPurchase::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $branch->id)->whereDate('purchase_date', $today->toDateString())->count(),
            'sales_today' => PharmacySale::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $branch->id)->whereBetween('dispensed_at', [$start, $end])->count(),
        ];
        $data = compact('stock', 'alertBatches', 'summary', 'filters', 'today', 'warningDate');

        return $request->expectsJson() ? response()->json(['data' => $data]) : view('pharmacy.workspace', $data);
    }

    public function updateSettings(Request $request): JsonResponse|RedirectResponse
    {
        abort_unless($this->tenant->can('PHARMACY_SETTINGS.MANAGE'), 403);
        $data = $request->validate(['pharmacy_expiry_warning_days' => ['required', 'integer', 'between:1,730']]);
        $branch = $this->tenant->branch();
        $old = ['pharmacy_expiry_warning_days' => $branch->pharmacy_expiry_warning_days];
        $branch->updateOrFail($data);
        $this->audit->record('pharmacy_settings', 'updated', $branch, $old, $data);

        return $request->expectsJson() ? response()->json(['data' => ['pharmacy_expiry_warning_days' => $branch->fresh()->pharmacy_expiry_warning_days]]) : back()->with('status', 'Pharmacy expiry warning window updated.');
    }

    private function view(): void
    {
        abort_unless($this->tenant->can('PHARMACY_WORKSPACE.VIEW'), 403);
    }
}
