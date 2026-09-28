<?php

namespace App\Http\Controllers;

use App\Services\PharmacyReconciliationService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PharmacyReconciliationController extends Controller
{
    public function __construct(private TenantContext $tenant, private PharmacyReconciliationService $reconciliation) {}

    public function __invoke(Request $request): View|JsonResponse
    {
        abort_unless($this->tenant->can('PHARMACY_RECONCILIATION.VIEW'), 403);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['RECONCILED', 'DISCREPANCY'])]]);
        $report = $this->reconciliation->report($this->tenant->hospitalId(), $this->tenant->branchId(), $filters['q'] ?? null, $filters['status'] ?? null);

        return $request->expectsJson() ? response()->json(['data' => $report]) : view('pharmacy.reconciliation', compact('report', 'filters'));
    }
}
