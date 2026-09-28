<?php

namespace App\Http\Controllers;

use App\Jobs\SendNotificationEmail;
use App\Models\NotificationDelivery;
use App\Models\OperationalNotification;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationCenterController extends Controller
{
    public function __construct(private TenantContext $tenant) {}

    public function index(Request $request): View|JsonResponse
    {
        $notifications = $this->notifications($request)->with('deliveries')->latest()->paginate(30)->withQueryString();
        $failedDeliveries = $this->tenant->isAdmin()
            ? NotificationDelivery::whereHas('notification', fn ($query) => $query->where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId()))->where('status', 'FAILED')->with('notification.recipient:id,name,email')->latest('updated_at')->limit(50)->get()
            : collect();
        $data = compact('notifications', 'failedDeliveries');

        return $request->expectsJson() ? response()->json(['data' => $data, 'unread_count' => $this->notifications($request)->whereNull('read_at')->count()]) : view('notifications.index', $data);
    }

    public function read(Request $request, string $notification): JsonResponse|RedirectResponse
    {
        $notification = $this->notifications($request)->findOrFail($notification);
        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return $request->expectsJson() ? response()->json(['data' => $notification]) : redirect($notification->url ?: route('notifications.index'));
    }

    public function retry(string $delivery): RedirectResponse|JsonResponse
    {
        abort_unless($this->tenant->isAdmin(), 403);
        $delivery = NotificationDelivery::whereHas('notification', fn ($query) => $query->where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId()))->where('status', 'FAILED')->findOrFail($delivery);
        $delivery->update(['status' => 'QUEUED', 'failed_at' => null, 'last_error' => null]);
        SendNotificationEmail::dispatch($delivery)->afterCommit();

        return request()->expectsJson() ? response()->json(['data' => $delivery->fresh()]) : back()->with('status', 'Notification delivery queued for retry.');
    }

    private function notifications(Request $request): Builder
    {
        return OperationalNotification::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())->where('recipient_user_id', $request->user()->id);
    }
}
