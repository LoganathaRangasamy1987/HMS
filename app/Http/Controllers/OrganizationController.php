<?php

namespace App\Http\Controllers;

use App\Models\Hospital;
use App\Services\AuditService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrganizationController extends Controller
{
    public function __construct(private TenantContext $tenant, private AuditService $audit) {}

    public function edit(Request $request)
    {
        abort_unless($this->tenant->isAdmin(), 403);
        $hospital = $this->tenant->hospital();

        return $request->expectsJson()
            ? response()->json(['data' => $hospital])
            : view('organization.edit', compact('hospital'));
    }

    public function update(Request $request)
    {
        abort_unless($this->tenant->isAdmin(), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9-]+$/', Rule::unique('hospitals', 'code')->ignore($this->tenant->hospitalId())],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], ['code.unique' => 'This hospital code is unavailable.']);

        if ($validated['status'] !== 'active') {
            throw ValidationException::withMessages(['status' => 'Keep the current hospital active. Hospital closure requires a separate administrative process.']);
        }

        $hospital = DB::transaction(function () use ($validated) {
            $hospital = Hospital::whereKey($this->tenant->hospitalId())->lockForUpdate()->firstOrFail();
            abort_unless($this->tenant->isAdmin(), 403);
            $old = $hospital->toArray();
            $hospital->update($validated);
            $this->audit->record('organization', 'updated', $hospital, $old, $hospital->fresh()->toArray());

            return $hospital->fresh();
        });

        return $request->expectsJson()
            ? response()->json(['data' => $hospital])
            : redirect()->route('organization.edit')->with('status', 'Hospital details updated.');
    }
}
