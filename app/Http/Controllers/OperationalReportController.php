<?php

namespace App\Http\Controllers;

use App\Services\OperationalReportService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OperationalReportController extends Controller
{
    public function __construct(private TenantContext $tenant, private OperationalReportService $reports) {}

    public function __invoke(Request $request): View|JsonResponse
    {
        abort_unless($this->tenant->isAdmin(), 403);
        $filters = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $timezone = $this->tenant->branch()->timezone;
        $to = $filters['to'] ?? now($timezone)->toDateString();
        $from = $filters['from'] ?? CarbonImmutable::parse($to, $timezone)->subDays(29)->toDateString();
        if (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > 365) {
            throw ValidationException::withMessages(['from' => 'The report range cannot exceed 366 days.']);
        }
        $report = $this->reports->report($this->tenant->hospitalId(), $this->tenant->branchId(), $from, $to, $timezone);

        return $request->expectsJson() ? response()->json(['data' => $report]) : view('reports.operational', compact('report'));
    }
}
