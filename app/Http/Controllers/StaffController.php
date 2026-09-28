<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Hospital;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use App\Support\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    private const ROLES = ['HOSPITAL_ADMIN', 'DOCTOR', 'RECEPTIONIST'];

    public function __construct(private TenantContext $tenant, private AuditService $audit) {}

    public function index(Request $request)
    {
        abort_unless($this->tenant->isAdmin(), 403);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $staff = User::where('hospital_id', $this->tenant->hospitalId())->with(['memberships.branch', 'memberships.role'])
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(fn ($search) => $search->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%')->orWhere('mobile', 'like', '%'.$q.'%')))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('name')->paginate(15)->withQueryString();

        return $request->expectsJson() ? response()->json($staff) : view('staff.index', compact('staff'));
    }

    public function create()
    {
        abort_unless($this->tenant->isAdmin(), 403);

        return view('staff.form', [
            'staff' => new User(['status' => 'active']),
            'branches' => $this->branches(),
            'roles' => $this->roles(),
            'assignedMemberships' => [],
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($this->tenant->isAdmin(), 403);
        $this->normalizeEmail($request);

        try {
            $staff = DB::transaction(function () use ($request) {
                Hospital::whereKey($this->tenant->hospitalId())->lockForUpdate()->firstOrFail();
                abort_unless($this->tenant->isAdmin(), 403);
                $validated = $request->validate($this->rules(), $this->messages());
                $memberships = $this->normalizedMemberships($validated['memberships']);
                $this->assertActiveAccess($validated['status'], $memberships);
                $staff = User::create([
                    'hospital_id' => $this->tenant->hospitalId(),
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'mobile' => $validated['mobile'] ?? null,
                    'password' => Hash::make($validated['password']),
                    'status' => $validated['status'],
                ]);
                $this->syncMemberships($staff, $memberships);
                $this->audit->record('staff', 'created', $staff, null, $this->snapshot($staff));

                return $staff->load(['memberships.branch', 'memberships.role']);
            });
        } catch (QueryException $exception) {
            $this->translateDuplicateEmail($request, $exception);
        }

        return $request->expectsJson()
            ? response()->json(['data' => $staff], 201)
            : redirect()->route('staff.index')->with('status', 'Staff account created.');
    }

    public function edit(Request $request, string $staff)
    {
        abort_unless($this->tenant->isAdmin(), 403);
        $staff = $this->findStaff($staff)->load(['memberships.branch', 'memberships.role']);
        $assignedMemberships = $staff->memberships->mapWithKeys(fn ($membership) => [
            $membership->branch_id => ['role_id' => $membership->role_id, 'status' => $membership->status],
        ])->all();

        return $request->expectsJson()
            ? response()->json(['data' => $staff])
            : view('staff.form', ['staff' => $staff, 'branches' => $this->branches(), 'roles' => $this->roles(), 'assignedMemberships' => $assignedMemberships]);
    }

    public function update(Request $request, string $staff)
    {
        abort_unless($this->tenant->isAdmin(), 403);
        $this->normalizeEmail($request);

        try {
            $staff = DB::transaction(function () use ($request, $staff) {
                Hospital::whereKey($this->tenant->hospitalId())->lockForUpdate()->firstOrFail();
                abort_unless($this->tenant->isAdmin(), 403);
                $staff = $this->findStaff($staff);
                $validated = $request->validate($this->rules($staff), $this->messages());
                $memberships = $this->normalizedMemberships($validated['memberships']);
                $this->assertActiveAccess($validated['status'], $memberships);

                if ((int) $staff->id === (int) $request->user()->id) {
                    $adminRoleId = Role::where('name', 'HOSPITAL_ADMIN')->value('id');
                    $keepsCurrentAdmin = collect($memberships)->contains(fn ($membership) => (int) $membership['branch_id'] === (int) $this->tenant->branchId()
                        && (int) $membership['role_id'] === (int) $adminRoleId
                        && $membership['status'] === 'active'
                    );
                    if ($validated['status'] !== 'active' || ! $keepsCurrentAdmin) {
                        throw ValidationException::withMessages(['memberships' => 'Keep your account active and retain your administrator access to the current branch. Another administrator can change your access.']);
                    }
                }

                $old = $this->snapshot($staff);
                $attributes = collect($validated)->only(['name', 'email', 'mobile', 'status'])->all();
                if (! empty($validated['password'])) {
                    $attributes['password'] = Hash::make($validated['password']);
                    $attributes['remember_token'] = null;
                }
                $staff->forceFill($attributes)->save();
                $this->syncMemberships($staff, $memberships);
                $this->assertAdministratorRemains();
                $new = $this->snapshot($staff);
                if (! empty($validated['password'])) {
                    $new['password_changed'] = true;
                }
                $this->audit->record('staff', 'updated', $staff, $old, $new);

                return $staff->fresh()->load(['memberships.branch', 'memberships.role']);
            });
        } catch (QueryException $exception) {
            $this->translateDuplicateEmail($request, $exception, $staff);
        }

        return $request->expectsJson()
            ? response()->json(['data' => $staff])
            : redirect()->route('staff.index')->with('status', 'Staff account updated.');
    }

    private function findStaff(string $id): User
    {
        return User::where('hospital_id', $this->tenant->hospitalId())->findOrFail($id);
    }

    private function branches()
    {
        return Branch::where('hospital_id', $this->tenant->hospitalId())->where('status', 'active')->orderBy('name')->get();
    }

    private function roles()
    {
        return Role::whereIn('name', self::ROLES)->orderBy('label')->get();
    }

    private function normalizeEmail(Request $request): void
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }
    }

    private function rules(?User $staff = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($staff?->id)],
            'mobile' => ['nullable', 'string', 'max:30'],
            'password' => [$staff ? 'nullable' : 'required', 'string', 'min:12', 'max:128', 'confirmed'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'memberships' => ['required', 'array', 'min:1', 'max:100'],
            'memberships.*' => ['required', 'array:branch_id,role_id,status'],
            'memberships.*.branch_id' => ['required', 'integer', 'distinct', Rule::exists('branches', 'id')->where('hospital_id', $this->tenant->hospitalId())->where('status', 'active')],
            'memberships.*.role_id' => ['required', 'integer', Rule::exists('roles', 'id')->whereIn('name', self::ROLES)],
            'memberships.*.status' => ['sometimes', 'required', Rule::in(['active', 'inactive'])],
        ];
    }

    private function messages(): array
    {
        return [
            'email.unique' => 'This email address is unavailable.',
            'memberships.*.branch_id.exists' => 'Choose an active branch belonging to this hospital.',
            'memberships.*.branch_id.distinct' => 'Choose each branch only once.',
            'memberships.*.role_id.exists' => 'Choose a supported staff role.',
        ];
    }

    private function normalizedMemberships(array $memberships): array
    {
        return array_map(fn ($membership) => [
            'branch_id' => (int) $membership['branch_id'],
            'role_id' => (int) $membership['role_id'],
            'status' => $membership['status'] ?? 'active',
        ], $memberships);
    }

    private function assertActiveAccess(string $status, array $memberships): void
    {
        if ($status === 'active' && ! collect($memberships)->contains('status', 'active')) {
            throw ValidationException::withMessages(['memberships' => 'An active staff account must have at least one active branch membership.']);
        }
    }

    private function syncMemberships(User $staff, array $memberships): void
    {
        // Preserve membership IDs when access changes; omitted memberships become inactive.
        Membership::where('hospital_id', $this->tenant->hospitalId())->where('user_id', $staff->id)
            ->whereNotIn('branch_id', array_column($memberships, 'branch_id'))->update(['status' => 'inactive']);

        foreach ($memberships as $membership) {
            Membership::updateOrCreate([
                'hospital_id' => $this->tenant->hospitalId(),
                'user_id' => $staff->id,
                'branch_id' => $membership['branch_id'],
            ], ['role_id' => $membership['role_id'], 'status' => $membership['status']]);
        }
    }

    private function assertAdministratorRemains(): void
    {
        $hasAdmin = Membership::where('hospital_id', $this->tenant->hospitalId())->where('status', 'active')
            ->whereHas('role', fn ($query) => $query->where('name', 'HOSPITAL_ADMIN'))
            ->whereHas('user', fn ($query) => $query->where('hospital_id', $this->tenant->hospitalId())->where('status', 'active'))
            ->whereHas('branch', fn ($query) => $query->where('hospital_id', $this->tenant->hospitalId())->where('status', 'active'))
            ->exists();

        if (! $hasAdmin) {
            throw ValidationException::withMessages(['memberships' => 'The hospital must retain at least one active administrator with access to an active branch.']);
        }
    }

    private function snapshot(User $staff): array
    {
        return [
            ...$staff->only(['id', 'hospital_id', 'name', 'email', 'mobile', 'status']),
            'memberships' => Membership::where('hospital_id', $this->tenant->hospitalId())->where('user_id', $staff->id)
                ->orderBy('branch_id')->get(['branch_id', 'role_id', 'status'])->toArray(),
        ];
    }

    private function translateDuplicateEmail(Request $request, QueryException $exception, string|User|null $staff = null): never
    {
        $staffId = $staff instanceof User ? $staff->id : $staff;
        if (in_array((string) $exception->getCode(), ['23000', '23505'], true)
            && User::where('email', $request->input('email'))->when($staffId, fn ($query) => $query->where('id', '<>', $staffId))->exists()) {
            throw ValidationException::withMessages(['email' => 'This email address is unavailable.']);
        }

        throw $exception;
    }
}
