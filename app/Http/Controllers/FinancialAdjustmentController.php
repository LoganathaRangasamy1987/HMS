<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\FinancialAdjustmentService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FinancialAdjustmentController extends Controller
{
    public function __construct(private TenantContext $tenant, private FinancialAdjustmentService $adjustments) {}

    public function adjust(Request $request, string $invoice): JsonResponse|RedirectResponse
    {
        $invoice = $this->invoice($invoice);
        $result = $this->adjustments->adjust($invoice, $this->validated($request, true));

        return $this->response($request, $result, 'Adjustment recorded.');
    }

    public function refund(Request $request, string $invoice, string $payment): JsonResponse|RedirectResponse
    {
        $invoice = $this->invoice($invoice);
        $payment = Payment::where('invoice_id', $invoice->id)->findOrFail($payment);
        $result = $this->adjustments->refund($invoice, $payment, $this->validated($request));

        return $this->response($request, $result, 'Refund recorded.');
    }

    public function void(Request $request, string $invoice): JsonResponse|RedirectResponse
    {
        $invoice = $this->invoice($invoice);
        $result = $this->adjustments->void($invoice, $this->validated($request, false, false));

        return $this->response($request, $result, 'Invoice voided.');
    }

    private function invoice(string $invoice): Invoice
    {
        abort_unless($this->tenant->can('FINANCIAL_ADJUSTMENT.MANAGE'), 403);

        return Invoice::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())->findOrFail($invoice);
    }

    /** @return array<string, string> */
    private function validated(Request $request, bool $withType = false, bool $withAmount = true): array
    {
        if ($request->filled('reason')) {
            $request->merge(['reason' => trim($request->input('reason'))]);
        }
        $rules = ['reason' => ['required', 'string', 'min:3', 'max:500'], 'request_key' => ['required', 'uuid']];
        if ($withAmount) {
            $rules['amount'] = ['required', 'decimal:0,2', 'gt:0', 'between:0,999999999999.99'];
        }
        if ($withType) {
            $rules['type'] = ['required', Rule::in(['CREDIT', 'DEBIT'])];
        }

        return $request->validate($rules);
    }

    /** @param array{adjustment: mixed, invoice: Invoice, summary: array<string, string>, replayed: bool} $result */
    private function response(Request $request, array $result, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['data' => $result['adjustment'], 'invoice' => ['id' => $result['invoice']->id, 'status' => $result['invoice']->status, ...$result['summary']], 'replayed' => $result['replayed']], $result['replayed'] ? 200 : 201);
        }

        return redirect()->route('invoices.show', $result['invoice'])->with('status', $result['replayed'] ? 'Financial action was already recorded.' : $message);
    }
}
