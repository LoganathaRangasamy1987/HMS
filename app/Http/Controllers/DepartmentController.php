<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Hospital;
use App\Services\AuditService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function __construct(private TenantContext $tenant, private AuditService $audit) {}

    public function index(Request $request)
    {
        abort_unless($this->tenant->isAdmin(), 403);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $departments = Department::where('hospital_id', $this->tenant->hospitalId())->with('branch')
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where('name', 'like', '%'.$q.'%'))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('name')->paginate(15)->withQueryString();

        return $request->expectsJson() ? response()->json($departments) : view('departments.index', compact('departments'));
    }

    public function create()
    {
        abort_unless($this->tenant->isAdmin(), 403);

        return view('departments.form', ['department' => new Department(['branch_id' => $this->tenant->branchId(), 'status' => 'active']), 'branches' => $this->branches()]);
    }

    public function store(Request $request)
    {
        abort_unless($this->tenant->isAdmin(), 403);
        $department = DB::transaction(function () use ($request) {
            Hospital::whereKey($this->tenant->hospitalId())->lockForUpdate()->firstOrFail();
            abort_unless($this->tenant->isAdmin(), 403);
            $validated = $request->validate($this->rules($request));
            $department = Department::create([...$validated, 'hospital_id' => $this->tenant->hospitalId()]);
            $this->audit->record('departments', 'created', $department, null, $department->toArray());

            return $department;
        });

        return $request->expectsJson()
            ? response()->json(['data' => $department->load('branch')], 201)
            : redirect()->route('departments.index')->with('status', 'Department created.');
    }

    public function edit(Request $request, string $department)
    {
        abort_unless($this->tenant->isAdmin(), 403);
        $department = $this->findDepartment($department);

        return $request->expectsJson()
            ? response()->json(['data' => $department->load('branch')])
            : view('departments.form', ['department' => $department, 'branches' => $this->branches()]);
    }

    public function update(Request $request, string $department)
    {
        abort_unless($this->tenant->isAdmin(), 403);
        $department = DB::transaction(function () use ($request, $department) {
            Hospital::whereKey($this->tenant->hospitalId())->lockForUpdate()->firstOrFail();
            abort_unless($this->tenant->isAdmin(), 403);
            $department = $this->findDepartment($department);
            $validated = $request->validate($this->rules($request, $department));
            $old = $department->toArray();
            $department->update($validated);
            $this->audit->record('departments', 'updated', $department, $old, $department->fresh()->toArray());

            return $department->fresh();
        });

        return $request->expectsJson()
            ? response()->json(['data' => $department->load('branch')])
            : redirect()->route('departments.index')->with('status', 'Department updated.');
    }

    private function findDepartment(string $id): Department
    {
        return Department::where('hospital_id', $this->tenant->hospitalId())->findOrFail($id);
    }

    private function branches()
    {
        return Branch::where('hospital_id', $this->tenant->hospitalId())->where('status', 'active')->orderBy('name')->get();
    }

    private function rules(Request $request, ?Department $department = null): array
    {
        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('hospital_id', $this->tenant->hospitalId())->where('status', 'active')],
            'name' => ['required', 'string', 'max:150', Rule::unique('departments', 'name')->where('hospital_id', $this->tenant->hospitalId())->where('branch_id', is_scalar($request->input('branch_id')) ? $request->input('branch_id') : null)->ignore($department?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
