<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContextController extends Controller
{
    public function update(Request $request, TenantContext $tenant, AuditService $audit): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['membership_id' => ['required', 'integer']]);
        $membership = $request->user()->activeMemberships()->with(['hospital', 'branch', 'role'])->findOrFail($data['membership_id']);
        $tenant->set($membership);
        $audit->record('access', 'branch_selected', $membership);
        $request->session()->put('membership_id', $membership->id);
        $request->session()->regenerate();

        return $request->expectsJson() ? response()->json(['data' => $membership]) : redirect()->route('dashboard')->with('status', 'Switched to '.$membership->branch->name.'.');
    }
}
