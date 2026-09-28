<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\Patient;
use App\Services\AppointmentService;
use App\Services\EncounterService;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function __construct(private TenantContext $tenant, private AppointmentService $appointments, private EncounterService $encounters) {}

    public function index(Request $request): View|JsonResponse
    {
        abort_unless($this->tenant->can('APPOINTMENT.VIEW'), 403);
        $filters = $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'status' => ['nullable', Rule::in(['BOOKED', 'CONFIRMED', 'CHECKED_IN', 'WAITING', 'CONSULTING', 'COMPLETED', 'CANCELLED', 'NO_SHOW'])], 'doctor_profile_id' => ['nullable', 'integer'], 'department_id' => ['nullable', 'integer']]);
        $selectedDate = $filters['date'] ?? ($request->expectsJson() ? null : now($this->tenant->branch()->timezone)->toDateString());
        $query = Appointment::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())
            ->when($this->isDoctor(), fn (Builder $query) => $query->whereHas('doctorProfile', fn (Builder $profile) => $profile->where('user_id', auth()->id())))
            ->when($selectedDate, fn (Builder $query, string $date) => $query->whereDate('appointment_date', $date))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['doctor_profile_id'] ?? null, fn (Builder $query, int|string $doctor) => $query->where('doctor_profile_id', $doctor))
            ->when($filters['department_id'] ?? null, fn (Builder $query, int|string $department) => $query->whereHas('doctorProfile', fn (Builder $profile) => $profile->where('department_id', $department)))
            ->with(['patient:id,uhid,first_name,last_name', 'doctorProfile.user:id,name'])->orderBy('appointment_date')->orderBy('token_number');
        $appointments = $query->paginate(15)->withQueryString();
        $data = ['appointments' => $appointments, 'selectedDate' => $selectedDate, ...$this->formData()];

        return $request->expectsJson() ? response()->json($appointments) : view('appointments.index', $data);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->manage();
        $data = $this->bookingData($request);
        $patient = Patient::where('hospital_id', $this->tenant->hospitalId())->where('status', 'active')->findOrFail($data['patient_id']);
        $doctor = $this->doctor($data['doctor_profile_id']);
        $appointment = $this->appointments->book($this->tenant->hospitalId(), $this->tenant->branchId(), $patient, $doctor, $request->user(), $data);

        return $request->expectsJson() ? response()->json(['data' => $appointment], $appointment->wasRecentlyCreated ? 201 : 200) : redirect()->route('appointments.index')->with('status', 'Appointment booked.');
    }

    public function reschedule(Request $request, string $appointment): JsonResponse|RedirectResponse
    {
        $this->manage();
        $appointment = $this->appointment($appointment);
        $data = $request->validate(['doctor_profile_id' => ['required', 'integer'], 'appointment_date' => ['required', 'date_format:Y-m-d'], 'starts_at' => ['required', 'date_format:H:i']]);
        $appointment = $this->appointments->reschedule($appointment, $this->doctor($data['doctor_profile_id']), $data);

        return $request->expectsJson() ? response()->json(['data' => $appointment]) : back()->with('status', 'Appointment rescheduled.');
    }

    public function cancel(Request $request, string $appointment): JsonResponse|RedirectResponse
    {
        $this->manage();
        $data = $request->validate(['cancellation_reason' => ['required', 'string', 'max:500']]);
        $appointment = $this->appointments->cancel($this->appointment($appointment), $data['cancellation_reason']);

        return $request->expectsJson() ? response()->json(['data' => $appointment]) : back()->with('status', 'Appointment cancelled.');
    }

    public function updateStatus(Request $request, string $appointment): JsonResponse|RedirectResponse
    {
        abort_unless($this->tenant->can('APPOINTMENT.VIEW'), 403);
        $data = $request->validate(['status' => ['required', Rule::in(['CONFIRMED', 'CHECKED_IN', 'WAITING', 'CONSULTING', 'COMPLETED', 'NO_SHOW'])]]);
        $appointment = $this->appointment($appointment);
        $doctorActor = $this->isDoctor();
        if ($doctorActor) {
            abort_unless($appointment->doctorProfile()->where('user_id', auth()->id())->exists(), 403);
        } else {
            $this->manage();
        }
        if ($doctorActor && $data['status'] === 'CONSULTING') {
            $result = $this->encounters->open($appointment, $request->user());

            return $request->expectsJson()
                ? response()->json(['data' => $result['appointment'], 'encounter' => $result['encounter'], 'replayed' => $result['replayed']])
                : redirect()->route('consultations.show', $result['encounter'])->with('status', $result['replayed'] ? 'Consultation already open.' : 'Consultation started.');
        }
        $appointment = $this->appointments->transition($appointment, $data['status'], $doctorActor);

        return $request->expectsJson() ? response()->json(['data' => $appointment]) : back()->with('status', 'Queue status updated.');
    }

    /** @return array<string, mixed> */
    private function bookingData(Request $request): array
    {
        return $request->validate(['patient_id' => ['required', 'integer'], 'doctor_profile_id' => ['required', 'integer'], 'appointment_date' => ['required', 'date_format:Y-m-d'], 'starts_at' => ['required', 'date_format:H:i'], 'type' => ['required', Rule::in(['NEW', 'FOLLOWUP', 'WALK_IN', 'ONLINE'])], 'reason' => ['nullable', 'string', 'max:500'], 'request_key' => ['nullable', 'uuid']]);
    }

    private function doctor(int|string $id): DoctorProfile
    {
        return DoctorProfile::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())->where('status', 'active')->with('branch')->findOrFail($id);
    }

    private function appointment(string $id): Appointment
    {
        return Appointment::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())->findOrFail($id);
    }

    private function manage(): void
    {
        abort_unless($this->tenant->can('APPOINTMENT.MANAGE'), 403);
    }

    private function isDoctor(): bool
    {
        return $this->tenant->membership()->role->name === 'DOCTOR';
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        $doctors = DoctorProfile::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())->where('status', 'active')
            ->when($this->isDoctor(), fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->with(['user:id,name', 'department:id,name'])->get();

        return ['patients' => Patient::where('hospital_id', $this->tenant->hospitalId())->where('status', 'active')->orderBy('first_name')->get(), 'doctors' => $doctors, 'departments' => $doctors->pluck('department')->unique('id')->sortBy('name')->values()];
    }
}
