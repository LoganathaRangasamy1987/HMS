<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\PaymentService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(private TenantContext $tenant, private PaymentService $payments) {}

    public function store(Request $request, string $invoice): JsonResponse|RedirectResponse
    {
        abort_unless($this->tenant->can('PAYMENT.MANAGE'), 403);
        $invoice = Invoice::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())->findOrFail($invoice);
        if ($request->filled('reference')) {
            $request->merge(['reference' => trim($request->input('reference'))]);
        }
        $data = $request->validate([
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'between:0,999999999999.99'],
            'mode' => ['required', Rule::in(['CASH', 'UPI', 'CARD', 'BANK_TRANSFER'])],
            'reference' => [Rule::requiredIf(fn () => $request->input('mode') !== 'CASH'), 'nullable', 'string', 'max:100'],
            'request_key' => ['required', 'uuid'],
        ]);
        $result = $this->payments->collect($invoice, $data);

        return $request->expectsJson() ? response()->json(['data' => $result['payment'], 'invoice' => ['id' => $result['invoice']->id, 'status' => $result['invoice']->status, ...$result['summary']], 'replayed' => $result['replayed']], $result['replayed'] ? 200 : 201) : redirect()->route('invoices.show', $invoice)->with('status', $result['replayed'] ? 'Payment was already recorded.' : 'Payment recorded.');
    }
}
