<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Encounter;
use App\Services\EncounterService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;

class EncounterController extends Controller
{
    public function __construct(private TenantContext $tenant, private EncounterService $encounters) {}

    public function store(string $appointment): JsonResponse
    {
        $this->authorizeClinical();
        $appointment = Appointment::query()
            ->where('hospital_id', $this->tenant->hospitalId())
            ->where('branch_id', $this->tenant->branchId())
            ->findOrFail($appointment);
        $result = $this->encounters->open($appointment, auth()->user());

        return response()->json(['data' => $result['encounter'], 'appointment' => $result['appointment'], 'replayed' => $result['replayed']], $result['replayed'] ? 200 : 201);
    }

    public function show(string $encounter): JsonResponse
    {
        $this->authorizeClinical();
        $encounter = Encounter::query()
            ->where('hospital_id', $this->tenant->hospitalId())
            ->where('branch_id', $this->tenant->branchId())
            ->whereHas('doctorProfile', fn ($query) => $query->where('user_id', auth()->id()))
            ->with(['patient:id,uhid,first_name,last_name,date_of_birth,gender', 'appointment', 'doctorProfile.user:id,name', 'department:id,name'])
            ->findOrFail($encounter);

        return response()->json(['data' => $encounter]);
    }

    private function authorizeClinical(): void
    {
        abort_unless($this->tenant->can('ENCOUNTER.MANAGE'), 403);
    }
}
