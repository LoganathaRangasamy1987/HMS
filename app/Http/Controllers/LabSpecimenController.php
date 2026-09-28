<?php

namespace App\Http\Controllers;

use App\Models\LabOrder;
use App\Models\LabSpecimen;
use App\Services\LabSpecimenService;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LabSpecimenController extends Controller
{
    public function __construct(private TenantContext $tenant, private LabSpecimenService $specimens) {}

    public function collect(Request $request, string $order, string $item): JsonResponse|RedirectResponse
    {
        $this->manage();
        $order = $this->order($order);
        $specimen = $this->specimens->collect($order->items()->findOrFail($item));

        return $request->expectsJson() ? response()->json(['data' => $specimen], 201) : back()->with('status', "Specimen {$specimen->identifier} collected.");
    }

    public function receive(Request $request, string $order, string $specimen): JsonResponse|RedirectResponse
    {
        return $this->transition($request, $order, $specimen, 'receive', 'Specimen received.');
    }

    public function process(Request $request, string $order, string $specimen): JsonResponse|RedirectResponse
    {
        return $this->transition($request, $order, $specimen, 'startProcessing', 'Specimen processing started.');
    }

    public function reject(Request $request, string $order, string $specimen): JsonResponse|RedirectResponse
    {
        $this->manage();
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']])['reason'];
        $specimen = $this->specimens->reject($this->specimen($this->order($order), $specimen), $reason);

        return $request->expectsJson() ? response()->json(['data' => $specimen]) : back()->with('status', 'Specimen rejected; recollection is now required.');
    }

    public function label(string $order, string $specimen): View
    {
        $this->view();
        $order = $this->order($order);
        $specimen = $this->specimen($order, $specimen)->load(['order.patient:id,uhid,first_name,last_name', 'orderItem.versionDefinition.sampleType', 'collectedBy:id,name']);

        return view('laboratory.specimens.label', ['order' => $order, 'specimen' => $specimen, 'hospital' => $this->tenant->hospital(), 'branch' => $this->tenant->branch()]);
    }

    private function transition(Request $request, string $order, string $specimen, string $method, string $message): JsonResponse|RedirectResponse
    {
        $this->manage();
        $specimen = $this->specimens->{$method}($this->specimen($this->order($order), $specimen));

        return $request->expectsJson() ? response()->json(['data' => $specimen]) : back()->with('status', $message);
    }

    private function order(string $order): LabOrder
    {
        return LabOrder::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())
            ->when($this->tenant->membership()->role->name === 'DOCTOR', fn (Builder $query) => $query->whereHas('doctorProfile', fn (Builder $query) => $query->where('user_id', auth()->id())))
            ->findOrFail($order);
    }

    private function specimen(LabOrder $order, string $specimen): LabSpecimen
    {
        return $order->specimens()->findOrFail($specimen);
    }

    private function view(): void
    {
        abort_unless($this->tenant->can('LAB_SPECIMEN.VIEW'), 403);
    }

    private function manage(): void
    {
        abort_unless($this->tenant->can('LAB_SPECIMEN.MANAGE'), 403);
    }
}
