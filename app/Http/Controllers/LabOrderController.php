<?php

namespace App\Http\Controllers;

use App\Models\DoctorProfile;
use App\Models\Encounter;
use App\Models\LabOrder;
use App\Models\LabTest;
use App\Models\Patient;
use App\Services\LabOrderService;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LabOrderController extends Controller
{
    public function __construct(private TenantContext $tenant, private LabOrderService $orders) {}

    public function index(Request $request): View|JsonResponse
    {
        $this->view();
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['ORDERED', 'PARTIALLY_COLLECTED', 'COLLECTED', 'RECEIVED', 'PROCESSING', 'RECOLLECTION_REQUIRED', 'RESULT_PARTIAL', 'VERIFIED', 'CANCELLED'])]]);
        $orders = $this->scope()->with(['patient:id,uhid,first_name,last_name', 'doctorProfile.user:id,name', 'invoice:id,number,status,total'])
            ->when($filters['q'] ?? null, function (Builder $query, string $q): void {
                $term = '%'.trim($q).'%';
                $query->where(fn (Builder $query) => $query->where('number', 'like', $term)->orWhereHas('patient', fn (Builder $query) => $query->where('uhid', 'like', $term)->orWhere('first_name', 'like', $term)->orWhere('last_name', 'like', $term)));
            })->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))->latest('ordered_at')->paginate(15)->withQueryString();

        return $request->expectsJson() ? response()->json($orders) : view('laboratory.orders.index', compact('orders', 'filters'));
    }

    public function create(): View
    {
        $this->manage();

        return view('laboratory.orders.create', $this->formData());
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->manage();
        $order = $this->orders->create($request->validate([
            'patient_id' => ['required', 'integer'], 'encounter_id' => ['nullable', 'integer'], 'doctor_profile_id' => ['required', 'integer'],
            'test_ids' => ['required', 'array', 'min:1', 'max:50'], 'test_ids.*' => ['required', 'integer', 'distinct'], 'clinical_notes' => ['nullable', 'string', 'max:2000'],
        ]));

        return $request->expectsJson() ? response()->json(['data' => $order], 201) : redirect()->route('laboratory.orders.show', $order)->with('status', 'Laboratory order created and invoice issued.');
    }

    public function show(Request $request, string $order): View|JsonResponse
    {
        $this->view();
        $order = $this->scope()->with($this->orders->relations())->findOrFail($order);

        return $request->expectsJson() ? response()->json(['data' => $order]) : view('laboratory.orders.show', compact('order'));
    }

    public function cancel(Request $request, string $order): JsonResponse|RedirectResponse
    {
        $this->manage();
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $order = $this->orders->cancel($this->scope()->findOrFail($order), $data['reason']);

        return $request->expectsJson() ? response()->json(['data' => $order]) : back()->with('status', 'Laboratory order cancelled and unpaid invoice voided.');
    }

    private function scope(): Builder
    {
        return LabOrder::where('hospital_id', $this->tenant->hospitalId())
            ->where('branch_id', $this->tenant->branchId())
            ->when($this->tenant->membership()->role->name === 'DOCTOR', fn (Builder $query) => $query->whereHas('doctorProfile', fn (Builder $query) => $query->where('user_id', auth()->id())));
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        $hospitalId = $this->tenant->hospitalId();
        $branchId = $this->tenant->branchId();

        return [
            'patients' => Patient::forHospital($hospitalId)->where('status', 'active')->latest()->limit(200)->get(['id', 'uhid', 'first_name', 'last_name']),
            'doctors' => DoctorProfile::where('hospital_id', $hospitalId)->where('branch_id', $branchId)->where('status', 'active')->with('user:id,name')->orderBy('id')->get(),
            'encounters' => Encounter::where('hospital_id', $hospitalId)->where('branch_id', $branchId)->latest('opened_at')->limit(200)->get(['id', 'patient_id', 'doctor_profile_id', 'status', 'opened_at']),
            'tests' => LabTest::where('hospital_id', $hospitalId)->where('status', 'active')->whereNotNull('active_version_id')->with('activeVersion:id,lab_test_id,price,currency')->orderBy('name')->get(['id', 'code', 'name', 'active_version_id']),
        ];
    }

    private function view(): void
    {
        abort_unless($this->tenant->can('LAB_ORDER.VIEW'), 403);
    }

    private function manage(): void
    {
        abort_unless($this->tenant->can('LAB_ORDER.MANAGE'), 403);
    }
}
