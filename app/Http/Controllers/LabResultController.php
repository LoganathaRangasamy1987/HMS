<?php

namespace App\Http\Controllers;

use App\Models\LabResult;
use App\Models\LabSpecimen;
use App\Services\LabResultService;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LabResultController extends Controller
{
    public function __construct(private TenantContext $tenant, private LabResultService $results) {}

    public function start(Request $request, string $specimen): JsonResponse|RedirectResponse
    {
        $this->enter();
        $result = $this->results->start($this->specimen($specimen));

        return $request->expectsJson() ? response()->json(['data' => $result], 201) : redirect()->route('laboratory.results.show', $result)->with('status', 'Result draft opened.');
    }

    public function show(Request $request, string $result): View|JsonResponse
    {
        $this->view();
        $result = $this->result($result)->with($this->results->relations())->firstOrFail();

        return $request->expectsJson() ? response()->json(['data' => $result]) : view('laboratory.results.show', compact('result'));
    }

    public function update(Request $request, string $result): JsonResponse|RedirectResponse
    {
        $this->enter();
        $result = $this->results->save($this->result($result)->firstOrFail(), $request->validate([
            'values' => ['required', 'array', 'min:1', 'max:100'], 'values.*.parameter_id' => ['required', 'integer'],
            'values.*.value' => ['present'], 'values.*.flag' => ['nullable', 'string'],
        ])['values']);

        return $request->expectsJson() ? response()->json(['data' => $result]) : back()->with('status', 'Result values saved.');
    }

    public function finalize(Request $request, string $result): JsonResponse|RedirectResponse
    {
        $this->verify();
        $result = $this->results->finalize($this->result($result)->firstOrFail());

        return $request->expectsJson() ? response()->json(['data' => $result]) : redirect()->route('laboratory.results.show', $result)->with('status', 'Result verified and finalized.');
    }

    public function correct(Request $request, string $result): JsonResponse|RedirectResponse
    {
        $this->enter();
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']])['reason'];
        $correction = $this->results->correct($this->result($result)->firstOrFail(), $reason);

        return $request->expectsJson() ? response()->json(['data' => $correction], 201) : redirect()->route('laboratory.results.show', $correction)->with('status', 'Correction draft created.');
    }

    public function report(string $result): View
    {
        $this->view();
        $result = $this->result($result)->with($this->results->relations())->firstOrFail();
        abort_unless($result->status === 'FINAL', 409, 'Only finalized results can be printed.');

        return view('laboratory.results.report', ['result' => $result, 'hospital' => $this->tenant->hospital(), 'branch' => $this->tenant->branch()]);
    }

    private function specimen(string $specimen): LabSpecimen
    {
        return LabSpecimen::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())->findOrFail($specimen);
    }

    private function result(string $result): Builder
    {
        return LabResult::whereKey($result)->where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())
            ->when($this->tenant->membership()->role->name === 'DOCTOR', fn (Builder $query) => $query->whereHas('order.doctorProfile', fn (Builder $query) => $query->where('user_id', auth()->id())));
    }

    private function view(): void
    {
        abort_unless($this->tenant->can('LAB_RESULT.VIEW'), 403);
    }

    private function enter(): void
    {
        abort_unless($this->tenant->can('LAB_RESULT.ENTER'), 403);
    }

    private function verify(): void
    {
        abort_unless($this->tenant->can('LAB_RESULT.VERIFY'), 403);
    }
}
