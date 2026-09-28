<?php

namespace App\Http\Controllers;

use App\Services\BillingReconciliationService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingReconciliationController extends Controller
{
    public function __construct(private TenantContext $tenant, private BillingReconciliationService $reconciliation) {}

    public function __invoke(Request $request): View|JsonResponse
    {
        abort_unless($this->tenant->isAdmin(), 403);
        $filters = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = $filters['date'] ?? now($this->tenant->branch()->timezone)->toDateString();
        $report = $this->reconciliation->daily(
            $this->tenant->hospitalId(),
            $this->tenant->branchId(),
            $date,
            $this->tenant->branch()->timezone,
        );

        return $request->expectsJson() ? response()->json(['data' => $report]) : view('billing.reconciliation', compact('report'));
    }
}
