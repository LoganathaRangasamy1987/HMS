<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Encounter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EncounterService
{
    public function __construct(private AuditService $audit) {}

    /** @return array{encounter: Encounter, appointment: Appointment, replayed: bool} */
    public function open(Appointment $appointment, User $actor): array
    {
        return DB::transaction(function () use ($appointment, $actor): array {
            $appointment = Appointment::query()->with('doctorProfile')->lockForUpdate()->findOrFail($appointment->id);
            if ($appointment->doctorProfile->user_id !== $actor->id) {
                abort(403, 'Only the assigned doctor can open this encounter.');
            }
            $existing = Encounter::query()->where('appointment_id', $appointment->id)->first();
            if ($existing) {
                if ($appointment->status !== 'CONSULTING') {
                    throw ValidationException::withMessages(['appointment' => 'The existing encounter is not in a consultable appointment state.']);
                }

                return ['encounter' => $existing, 'appointment' => $appointment, 'replayed' => true];
            }
            if ($appointment->status !== 'WAITING') {
                throw ValidationException::withMessages(['appointment' => 'Only a waiting appointment can start a new encounter.']);
            }

            $oldAppointment = $appointment->toArray();
            $encounter = Encounter::create([
                'hospital_id' => $appointment->hospital_id,
                'branch_id' => $appointment->branch_id,
                'patient_id' => $appointment->patient_id,
                'appointment_id' => $appointment->id,
                'doctor_profile_id' => $appointment->doctor_profile_id,
                'department_id' => $appointment->doctorProfile->department_id,
                'encounter_type' => 'OPD',
                'status' => 'ACTIVE',
                'opened_at' => now(),
                'opened_by' => $actor->id,
            ]);
            $appointment->updateOrFail(['status' => 'CONSULTING']);
            $this->audit->record('encounters', 'opened', $encounter, null, $encounter->toArray());
            $this->audit->record('appointments', 'status_changed', $appointment, $oldAppointment, $appointment->fresh()->toArray());

            return ['encounter' => $encounter, 'appointment' => $appointment->fresh(), 'replayed' => false];
        }, attempts: 5);
    }
}
