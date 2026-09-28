<?php

namespace App\Http\Controllers;

use App\Models\LabNotification;
use App\Models\LabOrderItem;
use App\Models\LabResult;
use App\Models\LabSpecimen;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LabWorklistController extends Controller
{
    public function __construct(private TenantContext $tenant) {}

    public function __invoke(Request $request): View|JsonResponse
    {
        abort_unless($this->tenant->can('LAB_WORKLIST.VIEW'), 403);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $search = trim((string) ($filters['q'] ?? ''));
        $collection = collect();
        $processing = collect();
        $verification = collect();
        $reports = collect();

        if ($this->tenant->can('LAB_SPECIMEN.MANAGE')) {
            $collection = $this->itemScope()->whereIn('status', ['ORDERED', 'RECOLLECTION_REQUIRED'])
                ->with(['order.patient:id,uhid,first_name,last_name', 'versionDefinition.sampleType'])->when($search, fn (Builder $query) => $this->searchItems($query, $search))->oldest()->limit(100)->get();
            $processing = $this->specimenScope()->whereIn('status', ['COLLECTED', 'RECEIVED', 'PROCESSING'])
                ->with(['order.patient:id,uhid,first_name,last_name', 'orderItem:id,test_code,test_name'])->when($search, fn (Builder $query) => $this->searchSpecimens($query, $search))->oldest('collected_at')->limit(100)->get();
        }
        if ($this->tenant->can('LAB_RESULT.VERIFY')) {
            $verification = $this->resultScope()->where('status', 'DRAFT')->whereHas('values')
                ->with(['order.patient:id,uhid,first_name,last_name', 'orderItem:id,test_code,test_name', 'enteredBy:id,name'])->when($search, fn (Builder $query) => $this->searchResults($query, $search))->oldest('entered_at')->limit(100)->get();
        }
        if ($this->tenant->can('LAB_RESULT.VIEW')) {
            $reports = $this->resultScope()->where('status', 'FINAL')
                ->with(['order.patient:id,uhid,first_name,last_name', 'orderItem:id,test_code,test_name', 'verifiedBy:id,name'])->when($search, fn (Builder $query) => $this->searchResults($query, $search))
                ->orderByDesc('revision')->latest('verified_at')->limit(100)->get()->unique('lab_specimen_id')->values();
        }
        $notifications = LabNotification::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())->where('recipient_user_id', auth()->id())->latest()->limit(20)->get();
        $data = compact('collection', 'processing', 'verification', 'reports', 'notifications', 'filters');

        return $request->expectsJson() ? response()->json(['data' => $data, 'unread_notifications' => $notifications->whereNull('read_at')->count()]) : view('laboratory.worklist', $data);
    }

    private function itemScope(): Builder
    {
        return LabOrderItem::whereHas('order', fn (Builder $query) => $this->scopeOrders($query));
    }

    private function specimenScope(): Builder
    {
        return LabSpecimen::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId());
    }

    private function resultScope(): Builder
    {
        return LabResult::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())
            ->when($this->tenant->membership()->role->name === 'DOCTOR', fn (Builder $query) => $query->whereHas('order.doctorProfile', fn (Builder $query) => $query->where('user_id', auth()->id())));
    }

    private function scopeOrders(Builder $query): void
    {
        $query->where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())->where('status', '!=', 'CANCELLED');
    }

    private function searchItems(Builder $query, string $search): void
    {
        $term = "%{$search}%";
        $query->where(fn (Builder $query) => $query->where('test_code', 'like', $term)->orWhere('test_name', 'like', $term)->orWhereHas('order', fn (Builder $query) => $query->where('number', 'like', $term)->orWhereHas('patient', fn (Builder $query) => $query->where('uhid', 'like', $term)->orWhere('first_name', 'like', $term)->orWhere('last_name', 'like', $term))));
    }

    private function searchSpecimens(Builder $query, string $search): void
    {
        $term = "%{$search}%";
        $query->where(fn (Builder $query) => $query->where('identifier', 'like', $term)->orWhereHas('order', fn (Builder $query) => $query->where('number', 'like', $term)->orWhereHas('patient', fn (Builder $query) => $query->where('uhid', 'like', $term)->orWhere('first_name', 'like', $term)->orWhere('last_name', 'like', $term))));
    }

    private function searchResults(Builder $query, string $search): void
    {
        $term = "%{$search}%";
        $query->where(fn (Builder $query) => $query->whereHas('orderItem', fn (Builder $query) => $query->where('test_code', 'like', $term)->orWhere('test_name', 'like', $term))->orWhereHas('order', fn (Builder $query) => $query->where('number', 'like', $term)->orWhereHas('patient', fn (Builder $query) => $query->where('uhid', 'like', $term)->orWhere('first_name', 'like', $term)->orWhere('last_name', 'like', $term))));
    }
}
