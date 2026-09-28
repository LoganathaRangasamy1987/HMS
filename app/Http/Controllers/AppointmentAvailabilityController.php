<?php

namespace App\Http\Controllers;

use App\Models\DoctorProfile;
use App\Services\AppointmentService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentAvailabilityController extends Controller
{
    public function __construct(private TenantContext $tenant, private AppointmentService $appointments) {}

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->tenant->can('APPOINTMENT.MANAGE'), 403);
        $data = $request->validate([
            'doctor_profile_id' => ['required', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'days' => ['nullable', 'integer', 'between:1,60'],
        ]);
        $doctor = DoctorProfile::where('hospital_id', $this->tenant->hospitalId())
            ->where('branch_id', $this->tenant->branchId())
            ->where('status', 'active')
            ->with(['user:id,name', 'branch:id,timezone'])
            ->findOrFail($data['doctor_profile_id']);
        $from = $data['from'] ?? now($doctor->branch->timezone)->toDateString();
        $dates = $this->appointments->availableSlots($doctor, $from, (int) ($data['days'] ?? 30));

        return response()->json([
            'data' => [
                'doctor' => ['id' => $doctor->id, 'name' => $doctor->user->name],
                'timezone' => $doctor->branch->timezone,
                'dates' => $dates,
            ],
        ]);
    }
}
