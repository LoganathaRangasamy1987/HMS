<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use App\Services\DashboardMetricsService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private TenantContext $tenant, private DashboardMetricsService $metrics) {}

    public function __invoke(Request $request): View|JsonResponse
    {
        $isAdmin = $this->tenant->isAdmin();
        $branchesQuery = Branch::where('hospital_id', $this->tenant->hospitalId());
        $departmentsQuery = Department::where('hospital_id', $this->tenant->hospitalId());
        $staffQuery = User::where('hospital_id', $this->tenant->hospitalId());

        if (! $isAdmin) {
            $branchesQuery->whereKey($this->tenant->branchId());
            $departmentsQuery->where('branch_id', $this->tenant->branchId());
            $staffQuery->whereHas('memberships', fn ($query) => $query
                ->where('hospital_id', $this->tenant->hospitalId())
                ->where('branch_id', $this->tenant->branchId())
                ->where('status', 'active'));
        }

        $stats = [
            'branches' => (clone $branchesQuery)->where('status', 'active')->count(),
            'departments' => $departmentsQuery->where('status', 'active')->count(),
            'staff' => $staffQuery->where('status', 'active')->count(),
        ];
        $branches = $branchesQuery->orderBy('name')->get();
        $recentActivity = $isAdmin
            ? AuditLog::where('hospital_id', $this->tenant->hospitalId())->with('user')->latest('created_at')->latest('id')->limit(8)->get()
            : collect();
        $workflowCards = $this->metrics->cards($request->user()->id);

        return $request->expectsJson()
            ? response()->json(compact('stats', 'branches', 'recentActivity', 'workflowCards'))
            : view('dashboard', compact('stats', 'branches', 'recentActivity', 'workflowCards'));
    }
}
