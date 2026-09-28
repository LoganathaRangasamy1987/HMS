<?php

namespace App\Http\Controllers;

use App\Models\LabNotification;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LabNotificationController extends Controller
{
    public function __construct(private TenantContext $tenant) {}

    public function read(Request $request, string $notification): JsonResponse|RedirectResponse
    {
        $notification = LabNotification::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())->where('recipient_user_id', $request->user()->id)->findOrFail($notification);
        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return $request->expectsJson() ? response()->json(['data' => $notification]) : redirect($notification->url);
    }
}
