<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Department;
use App\Models\DoctorProfile;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DoctorProfileController extends Controller
{
    public function __construct(private TenantContext $tenant, private AuditService $audit) {}

    public function index(Request $request): View|JsonResponse
    {
        abort_unless($this->tenant->can('DOCTOR.VIEW'), 403);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $profiles = DoctorProfile::where('hospital_id', $this->tenant->hospitalId())
            ->when(
                $this->tenant->membership()->role->name === 'DOCTOR',
                fn (Builder $query) => $query->where('user_id', auth()->id())->where('branch_id', $this->tenant->branchId()),
            )
            ->with(['user:id,name,email', 'branch:id,name', 'department:id,name'])
            ->when($filters['q'] ?? null, fn (Builder $query, string $q) => $query->where(fn (Builder $search) => $search->where('registration_number', 'like', '%'.$q.'%')->orWhere('specialization', 'like', '%'.$q.'%')->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', '%'.$q.'%'))))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))->orderBy('id')->paginate(15)->withQueryString();

        return $request->expectsJson() ? response()->json($profiles) : view('doctors.index', compact('profiles'));
    }

    public function create(): View
    {
        $this->manage();

        return view('doctors.form', $this->formData(new DoctorProfile(['status' => 'active', 'consultation_fee' => 0])));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->manage();
        $validated = $this->validated($request);
        $profile = DB::transaction(function () use ($validated): DoctorProfile {
            $profile = DoctorProfile::create([...$validated, 'hospital_id' => $this->tenant->hospitalId()]);
            $this->audit->record('doctor_profiles', 'created', $profile, null, $profile->toArray());

            return $profile;
        });

        return $request->expectsJson() ? response()->json(['data' => $profile], 201) : redirect()->route('doctors.index')->with('status', 'Doctor profile created.');
    }

    public function edit(string $doctor): View
    {
        $this->manage();

        return view('doctors.form', $this->formData($this->profile($doctor)));
    }

    public function update(Request $request, string $doctor): JsonResponse|RedirectResponse
    {
        $this->manage();
        $profile = $this->profile($doctor);
        $validated = $this->validated($request, $profile);
        DB::transaction(function () use ($profile, $validated): void {
            $old = $profile->toArray();
            $profile->updateOrFail($validated);
            $this->audit->record('doctor_profiles', 'updated', $profile, $old, $profile->fresh()->toArray());
        });

        return $request->expectsJson() ? response()->json(['data' => $profile->fresh()]) : redirect()->route('doctors.index')->with('status', 'Doctor profile updated.');
    }

    private function validated(Request $request, ?DoctorProfile $profile = null): array
    {
        $hospitalId = $this->tenant->hospitalId();
        $validated = $request->validate(['branch_id' => ['required', Rule::exists('branches', 'id')->where(fn ($q) => $q->where('hospital_id', $hospitalId)->where('status', 'active'))], 'user_id' => ['required', Rule::exists('users', 'id')->where(fn ($q) => $q->where('hospital_id', $hospitalId)->where('status', 'active'))], 'department_id' => ['required', 'integer'], 'registration_number' => ['required', 'string', 'max:100', Rule::unique('doctor_profiles')->where('hospital_id', $hospitalId)->ignore($profile?->id)], 'qualification' => ['required', 'string', 'max:500'], 'specialization' => ['required', 'string', 'max:200'], 'consultation_fee' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999.99'], 'status' => ['required', Rule::in(['active', 'inactive'])]]);
        $doctorRole = Role::where('name', 'DOCTOR')->value('id');
        $hasMembership = User::whereKey($validated['user_id'])->where('hospital_id', $hospitalId)->whereHas('memberships', fn ($q) => $q->where('branch_id', $validated['branch_id'])->where('role_id', $doctorRole)->where('status', 'active'))->exists();
        $departmentExists = Department::whereKey($validated['department_id'])->where('hospital_id', $hospitalId)->where('branch_id', $validated['branch_id'])->where('status', 'active')->exists();
        abort_unless($hasMembership && $departmentExists, 422, 'Doctor membership and department must belong to the selected active branch.');

        return $validated;
    }

    private function profile(string $id): DoctorProfile
    {
        return DoctorProfile::where('hospital_id', $this->tenant->hospitalId())->findOrFail($id);
    }

    private function manage(): void
    {
        abort_unless($this->tenant->can('DOCTOR.MANAGE'), 403);
    }

    private function formData(DoctorProfile $profile): array
    {
        $hospitalId = $this->tenant->hospitalId();

        return ['profile' => $profile, 'branches' => Branch::where('hospital_id', $hospitalId)->where('status', 'active')->orderBy('name')->get(), 'departments' => Department::where('hospital_id', $hospitalId)->where('status', 'active')->with('branch:id,name')->orderBy('name')->get(), 'doctors' => User::where('hospital_id', $hospitalId)->where('status', 'active')->whereHas('memberships.role', fn ($q) => $q->where('name', 'DOCTOR'))->orderBy('name')->get()];
    }
}
