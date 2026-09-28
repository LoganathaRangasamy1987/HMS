<?php

namespace App\Http\Controllers;

use App\Services\GlobalSearchService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GlobalSearchController extends Controller
{
    public function __construct(private TenantContext $tenant, private GlobalSearchService $search) {}

    public function index(Request $request): View|JsonResponse
    {
        $filters = $this->filters($request, 50, false);
        [$patients, $invoices] = $this->allowedCategories($filters['category']);
        $results = $filters['q'] === ''
            ? ['query' => '', 'patients' => [], 'invoices' => [], 'counts' => ['patients' => 0, 'invoices' => 0]]
            : $this->search->search($this->tenant->hospitalId(), $this->tenant->branchId(), $filters['q'], $patients, $invoices, $filters['limit']);

        return $request->expectsJson() ? response()->json(['data' => $results]) : view('search.index', compact('results', 'filters', 'patients', 'invoices'));
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request, 500, true);
        [$patients, $invoices] = $this->allowedCategories($filters['category']);
        $results = $this->search->search($this->tenant->hospitalId(), $this->tenant->branchId(), $filters['q'], $patients, $invoices, $filters['limit']);

        return response()->streamDownload(function () use ($results): void {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, ['category', 'reference', 'name', 'mobile', 'status', 'amount']);
            foreach ($results['patients'] as $patient) {
                fputcsv($stream, ['patient', $this->safe($patient['uhid']), $this->safe($patient['name']), $this->safe($patient['mobile']), $patient['status'], '']);
            }
            foreach ($results['invoices'] as $invoice) {
                fputcsv($stream, ['invoice', $this->safe($invoice['number']), $this->safe($invoice['patient_name']), '', $invoice['status'], $invoice['total']]);
            }
            fclose($stream);
        }, 'search-results.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** @return array{q: string, category: string, limit: int} */
    private function filters(Request $request, int $maximum, bool $required): array
    {
        $validated = $request->validate(['q' => [$required ? 'required' : 'nullable', 'string', 'min:2', 'max:100'], 'category' => ['nullable', Rule::in(['all', 'patients', 'invoices'])], 'limit' => ['nullable', 'integer', 'min:1', 'max:'.$maximum]]);

        return ['q' => trim($validated['q'] ?? ''), 'category' => $validated['category'] ?? 'all', 'limit' => (int) ($validated['limit'] ?? min(20, $maximum))];
    }

    /** @return array{bool, bool} */
    private function allowedCategories(string $category): array
    {
        $canPatients = $this->tenant->can('PATIENT.VIEW');
        $canInvoices = $this->tenant->can('INVOICE.MANAGE');
        abort_unless($canPatients || $canInvoices, Response::HTTP_FORBIDDEN);
        abort_if($category === 'patients' && ! $canPatients, Response::HTTP_FORBIDDEN);
        abort_if($category === 'invoices' && ! $canInvoices, Response::HTTP_FORBIDDEN);

        return [$canPatients && $category !== 'invoices', $canInvoices && $category !== 'patients'];
    }

    private function safe(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }
}
