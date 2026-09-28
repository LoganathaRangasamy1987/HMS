<?php

namespace App\Http\Controllers;

use App\Models\DoctorProfile;
use App\Models\DoctorSchedule;
use App\Models\DoctorScheduleException;
use App\Models\DoctorUnavailability;
use App\Services\AuditService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DoctorAvailabilityController extends Controller
{
    public function __construct(private TenantContext $tenant, private AuditService $audit) {}

    public function show(Request $request, string $doctor): View|JsonResponse
    {
        abort_unless($this->tenant->can('DOCTOR_AVAILABILITY.VIEW'), 403);
        $profile = $this->profile($doctor)->load(['user:id,name', 'branch:id,name,timezone', 'schedules', 'unavailabilities', 'scheduleExceptions']);
        if ($this->tenant->membership()->role->name === 'DOCTOR') {
            abort_unless($profile->user_id === auth()->id() && $profile->branch_id === $this->tenant->branchId(), 403);
        }

        return $request->expectsJson() ? response()->json(['data' => $profile]) : view('doctors.availability', compact('profile'));
    }

    public function storeSchedule(Request $request, string $doctor): JsonResponse|RedirectResponse
    {
        $profile = $this->manageable($doctor);
        $data = $request->validate(['day_of_week' => ['required', 'integer', 'between:0,6'], 'starts_at' => ['required', 'date_format:H:i'], 'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'], 'slot_duration_minutes' => ['required', 'integer', 'between:5,240'], 'capacity_per_slot' => ['required', 'integer', 'between:1,100'], 'status' => ['required', Rule::in(['active', 'inactive'])]]);
        $overlap = $profile->schedules()->where('day_of_week', $data['day_of_week'])->where('status', 'active')->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at'])->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['starts_at' => 'This schedule overlaps an active weekly schedule.']);
        }
        $schedule = $this->createAudited($profile, DoctorSchedule::class, $data, 'doctor_schedules');

        return $this->created($request, $schedule, 'Weekly schedule added.');
    }

    public function storeUnavailability(Request $request, string $doctor): JsonResponse|RedirectResponse
    {
        $profile = $this->manageable($doctor);
        $data = $request->validate(['type' => ['required', Rule::in(['holiday', 'leave'])], 'starts_on' => ['required', 'date_format:Y-m-d'], 'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'], 'reason' => ['required', 'string', 'max:500']]);
        $record = $this->createAudited($profile, DoctorUnavailability::class, $data, 'doctor_unavailability');

        return $this->created($request, $record, 'Closure recorded.');
    }

    public function storeException(Request $request, string $doctor): JsonResponse|RedirectResponse
    {
        $profile = $this->manageable($doctor);
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'availability' => ['required', Rule::in(['available', 'unavailable'])], 'starts_at' => ['required_if:availability,available', 'nullable', 'date_format:H:i'], 'ends_at' => ['required_if:availability,available', 'nullable', 'date_format:H:i', 'after:starts_at'], 'slot_duration_minutes' => ['required_if:availability,available', 'nullable', 'integer', 'between:5,240'], 'capacity_per_slot' => ['required_if:availability,available', 'nullable', 'integer', 'between:1,100'], 'reason' => ['required', 'string', 'max:500']]);
        if ($data['availability'] === 'unavailable') {
            $data = [...$data, 'starts_at' => null, 'ends_at' => null, 'slot_duration_minutes' => null, 'capacity_per_slot' => null];
        }
        $record = DB::transaction(function () use ($profile, $data) {
            $existing = $profile->scheduleExceptions()->whereDate('date', $data['date'])->first();
            $old = $existing?->toArray();
            $record = $existing ?? $profile->scheduleExceptions()->make();
            $record->fill($data)->save();
            $this->audit->record('doctor_schedule_exceptions', $existing ? 'updated' : 'created', $record, $old, $record->toArray());

            return $record;
        });

        return $this->created($request, $record, 'Schedule exception saved.', $record->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, string $doctor, string $type, string $record): JsonResponse|RedirectResponse
    {
        $profile = $this->manageable($doctor);
        $model = match ($type) {
            'schedule' => DoctorSchedule::class,'closure' => DoctorUnavailability::class,'exception' => DoctorScheduleException::class,default => abort(404)
        };
        $item = $model::where('doctor_profile_id', $profile->id)->findOrFail($record);
        DB::transaction(function () use ($item, $type) {
            $module = match ($type) {
                'schedule' => 'doctor_schedules',
                'closure' => 'doctor_unavailability',
                'exception' => 'doctor_schedule_exceptions',
            };
            $this->audit->record($module, 'deleted', $item, $item->toArray());
            $item->delete();
        });

        return $request->expectsJson() ? response()->json(null, 204) : back()->with('status', 'Availability record removed.');
    }

    private function profile(string $id): DoctorProfile
    {
        return DoctorProfile::where('hospital_id', $this->tenant->hospitalId())->findOrFail($id);
    }

    private function manageable(string $id): DoctorProfile
    {
        $profile = $this->profile($id);
        abort_unless($this->tenant->isAdmin() || ($this->tenant->can('DOCTOR_AVAILABILITY.MANAGE') && $profile->user_id === auth()->id()), 403);

        return $profile;
    }

    private function createAudited(DoctorProfile $profile, string $model, array $data, string $module): object
    {
        return DB::transaction(function () use ($profile, $model, $data, $module) {
            $record = $model::create([...$data, 'doctor_profile_id' => $profile->id]);
            $this->audit->record($module, 'created', $record, null, $record->toArray());

            return $record;
        });
    }

    private function created(Request $request, object $record, string $message, int $status = 201): JsonResponse|RedirectResponse
    {
        return $request->expectsJson() ? response()->json(['data' => $record], $status) : back()->with('status', $message);
    }
}
