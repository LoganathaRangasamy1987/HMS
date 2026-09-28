<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Hospital;
use App\Models\Membership;
use App\Services\AuditService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BranchController extends Controller
{
    public function __construct(private TenantContext $tenant, private AuditService $audit) {}

    public function index(Request $request)
    {
        abort_unless($this->tenant->isAdmin(), 403);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $branches = Branch::where('hospital_id', $this->tenant->hospitalId())
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(fn ($search) => $search->where('name', 'like', '%'.$q.'%')->orWhere('code', 'like', '%'.$q.'%')->orWhere('city', 'like', '%'.$q.'%')))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('name')->paginate(15)->withQueryString();

        return $request->expectsJson() ? response()->json($branches) : view('branches.index', compact('branches'));
    }

    public function create()
    {
        abort_unless($this->tenant->isAdmin(), 403);

        return view('branches.form', ['branch' => new Branch(['status' => 'active'])]);
    }

    public function store(Request $request)
    {
        abort_unless($this->tenant->isAdmin(), 403);
        $branch = DB::transaction(function () use ($request) {
            Hospital::whereKey($this->tenant->hospitalId())->lockForUpdate()->firstOrFail();
            abort_unless($this->tenant->isAdmin(), 403);
            $validated = $request->validate($this->rules());
            $branch = Branch::create([...$validated, 'hospital_id' => $this->tenant->hospitalId()]);
            $this->audit->record('branches', 'created', $branch, null, $branch->toArray());

            return $branch;
        });

        return $request->expectsJson()
            ? response()->json(['data' => $branch], 201)
            : redirect()->route('branches.index')->with('status', 'Branch created.');
    }

    public function edit(Request $request, string $branch)
    {
        abort_unless($this->tenant->isAdmin(), 403);
        $branch = $this->findBranch($branch);

        return $request->expectsJson() ? response()->json(['data' => $branch]) : view('branches.form', compact('branch'));
    }

    public function update(Request $request, string $branch)
    {
        abort_unless($this->tenant->isAdmin(), 403);
        $branch = DB::transaction(function () use ($request, $branch) {
            Hospital::whereKey($this->tenant->hospitalId())->lockForUpdate()->firstOrFail();
            abort_unless($this->tenant->isAdmin(), 403);
            $branch = $this->findBranch($branch);
            $validated = $request->validate($this->rules($branch));

            if ($validated['status'] === 'inactive') {
                if ((int) $branch->id === (int) $this->tenant->branchId()) {
                    throw ValidationException::withMessages(['status' => 'Switch to another branch before deactivating the current branch.']);
                }
                $hasDepartments = Department::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $branch->id)->where('status', 'active')->exists();
                $hasMemberships = Membership::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $branch->id)->where('status', 'active')->exists();
                if ($hasDepartments || $hasMemberships) {
                    throw ValidationException::withMessages(['status' => 'Deactivate this branch’s departments and remove or deactivate its staff memberships before deactivating the branch.']);
                }
            }

            $old = $branch->toArray();
            $branch->update($validated);
            $this->audit->record('branches', 'updated', $branch, $old, $branch->fresh()->toArray());

            return $branch->fresh();
        });

        return $request->expectsJson()
            ? response()->json(['data' => $branch])
            : redirect()->route('branches.index')->with('status', 'Branch updated.');
    }

    private function findBranch(string $id): Branch
    {
        return Branch::where('hospital_id', $this->tenant->hospitalId())->findOrFail($id);
    }

    private function rules(?Branch $branch = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9-]+$/', Rule::unique('branches', 'code')->where('hospital_id', $this->tenant->hospitalId())->ignore($branch?->id)],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'timezone' => ['sometimes', 'required', 'timezone:all'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
