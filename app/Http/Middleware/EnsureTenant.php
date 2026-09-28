<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenant
{
    public function __construct(private TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $memberships = $request->user()->activeMemberships()->with(['hospital', 'branch', 'role.permissions'])->orderBy('id')->get();
        $membership = $memberships->firstWhere('id', (int) $request->session()->get('membership_id'));

        if (! $membership && ! $request->session()->has('membership_id')) {
            $membership = $memberships->first();

            if ($membership) {
                $request->session()->put('membership_id', $membership->id);
            }
        }

        abort_unless($membership, 403, 'Your branch access is no longer available. Sign in again or contact your administrator.');
        $this->tenant->set($membership);
        View::share(['tenant' => $this->tenant, 'availableMemberships' => $memberships]);

        return $next($request);
    }
}
