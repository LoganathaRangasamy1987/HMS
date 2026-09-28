<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\ServiceItem;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(private TenantContext $tenant, private InvoiceService $invoices, private PaymentService $payments) {}

    public function index(Request $request): View|JsonResponse
    {
        $this->authorizeBilling();
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['DRAFT', 'ISSUED', 'PARTIALLY_PAID', 'PAID', 'VOID'])],
            'outstanding' => ['nullable', 'boolean'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $query = $this->scope()
            ->with([
                'patient:id,uhid,first_name,last_name,mobile',
                'payments:id,invoice_id,amount',
                'payments.refunds:id,invoice_id,payment_id,amount,type',
                'financialAdjustments:id,invoice_id,amount,type',
            ])
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $term = '%'.trim($search).'%';
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('number', 'like', $term)->orWhereHas('patient', function (Builder $query) use ($term): void {
                        $query->where('uhid', 'like', $term)
                            ->orWhere('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhere('mobile', 'like', $term);
                    });
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($request->boolean('outstanding'), fn (Builder $query): Builder => $query->whereIn('status', ['ISSUED', 'PARTIALLY_PAID']))
            ->when($filters['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date));
        $invoices = $query->latest()->paginate(15)->withQueryString();
        $paymentSummaries = $invoices->getCollection()->mapWithKeys(fn (Invoice $invoice): array => [$invoice->id => $this->payments->summary($invoice)]);
        $outstandingCount = $this->scope()->whereIn('status', ['ISSUED', 'PARTIALLY_PAID'])->count();

        return $request->expectsJson() ? response()->json($invoices) : view('invoices.index', compact('invoices', 'paymentSummaries', 'outstandingCount', 'filters'));
    }

    public function create(): View
    {
        $this->authorizeBilling();

        return view('invoices.form', $this->formData());
    }

    public function show(Request $request, string $invoice): View|JsonResponse
    {
        $this->authorizeBilling();
        $invoice = $this->scope()->with([
            'lines',
            'patient:id,uhid,first_name,last_name',
            'appointment',
            'payments' => fn ($query) => $query->select(['id', 'invoice_id', 'amount', 'mode', 'reference', 'received_at', 'received_by'])->orderBy('received_at'),
            'payments.refunds',
            'financialAdjustments' => fn ($query) => $query->orderBy('recorded_at'),
        ])->findOrFail($invoice);
        $paymentSummary = $this->payments->summary($invoice);

        return $request->expectsJson() ? response()->json(['data' => $invoice, 'payment_summary' => $paymentSummary]) : view('invoices.show', compact('invoice', 'paymentSummary'));
    }

    public function edit(string $invoice): View
    {
        $this->authorizeBilling();
        $invoice = $this->scope()->with('lines')->findOrFail($invoice);
        abort_unless($invoice->status === 'DRAFT', 409, 'Issued invoices cannot be edited.');

        return view('invoices.form', $this->formData($invoice));
    }

    public function printInvoice(string $invoice): View
    {
        $this->authorizeBilling();
        $invoice = $this->printableInvoice($invoice);

        return view('invoices.print.invoice', [
            'invoice' => $invoice,
            'paymentSummary' => $this->payments->summary($invoice),
            'hospital' => $this->tenant->hospital(),
            'branch' => $this->tenant->branch(),
        ]);
    }

    public function printReceipt(string $invoice, string $payment): View
    {
        $this->authorizeBilling();
        $invoice = $this->printableInvoice($invoice);
        $payment = $invoice->payments->firstWhere('id', (int) $payment);
        abort_unless($payment instanceof Payment, 404);

        return view('invoices.print.receipt', [
            'invoice' => $invoice,
            'payment' => $payment,
            'paymentSummary' => $this->payments->summary($invoice),
            'hospital' => $this->tenant->hospital(),
            'branch' => $this->tenant->branch(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeBilling();
        $invoice = $this->invoices->saveDraft($this->validated($request));

        return $request->expectsJson() ? response()->json(['data' => $invoice], 201) : redirect()->route('invoices.show', $invoice)->with('status', 'Invoice draft saved.');
    }

    public function update(Request $request, string $invoice): RedirectResponse|JsonResponse
    {
        $this->authorizeBilling();
        $invoice = $this->invoices->saveDraft($this->validated($request), $this->scope()->findOrFail($invoice));

        return $request->expectsJson() ? response()->json(['data' => $invoice]) : redirect()->route('invoices.show', $invoice)->with('status', 'Invoice draft updated.');
    }

    public function issue(Request $request, string $invoice): RedirectResponse|JsonResponse
    {
        $this->authorizeBilling();
        $invoice = $this->invoices->issue($this->scope()->findOrFail($invoice));

        return $request->expectsJson() ? response()->json(['data' => $invoice]) : redirect()->route('invoices.show', $invoice)->with('status', 'Invoice issued.');
    }

    private function scope(): Builder
    {
        return Invoice::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId());
    }

    private function authorizeBilling(): void
    {
        abort_unless($this->tenant->can('INVOICE.MANAGE'), 403);
    }

    private function printableInvoice(string $invoice): Invoice
    {
        $invoice = $this->scope()->with([
            'lines',
            'patient:id,uhid,first_name,last_name,mobile,address',
            'payments' => fn ($query) => $query->with('refunds')->orderBy('received_at'),
            'financialAdjustments' => fn ($query) => $query->orderBy('recorded_at'),
        ])->findOrFail($invoice);
        abort_if($invoice->status === 'DRAFT', 409, 'Draft invoices cannot be printed.');

        return $invoice;
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        if (! $request->expectsJson()) {
            $request->merge(['lines' => array_values(array_filter($request->input('lines', []), fn ($line) => ! empty($line['service_item_id'])))]);
        }

        return $request->validate([
            'patient_id' => ['required', 'integer', Rule::exists('patients', 'id')->where('hospital_id', $this->tenant->hospitalId())],
            'appointment_id' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1', 'max:20'],
            'lines.*.service_item_id' => ['required', 'integer', 'distinct'],
            'lines.*.quantity' => ['required', 'integer', 'between:1,1000'],
        ]);
    }

    /** @return array<string, mixed> */
    private function formData(?Invoice $invoice = null): array
    {
        return [
            'invoice' => $invoice,
            'patients' => Patient::forHospital($this->tenant->hospitalId())->where('status', 'active')->orderByDesc('id')->limit(200)->get(['id', 'uhid', 'first_name', 'last_name']),
            'appointments' => Appointment::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())->whereNotIn('status', ['CANCELLED', 'NO_SHOW'])->latest()->limit(200)->get(['id', 'patient_id', 'appointment_date', 'token_number']),
            'services' => ServiceItem::where('hospital_id', $this->tenant->hospitalId())->where('status', 'active')->whereDoesntHave('branchPrices', fn ($query) => $query->where('branch_id', $this->tenant->branchId())->where('is_available', false))->orderBy('name')->get(['id', 'code', 'name']),
        ];
    }
}
