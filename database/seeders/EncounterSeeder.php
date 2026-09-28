<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Encounter;
use Illuminate\Database\Seeder;
use LogicException;

class EncounterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Fictional encounters are only available in local/testing environments.');
        }

        Appointment::query()->whereIn('status', ['CONSULTING', 'COMPLETED'])->with('doctorProfile')->each(function (Appointment $appointment): void {
            Encounter::query()->firstOrCreate(['appointment_id' => $appointment->id], [
                'hospital_id' => $appointment->hospital_id,
                'branch_id' => $appointment->branch_id,
                'patient_id' => $appointment->patient_id,
                'doctor_profile_id' => $appointment->doctor_profile_id,
                'department_id' => $appointment->doctorProfile->department_id,
                'encounter_type' => 'OPD',
                'status' => $appointment->status === 'COMPLETED' ? 'CLOSED' : 'ACTIVE',
                'opened_at' => $appointment->updated_at,
                'opened_by' => $appointment->doctorProfile->user_id,
                'closed_at' => $appointment->status === 'COMPLETED' ? $appointment->updated_at : null,
                'closed_by' => $appointment->status === 'COMPLETED' ? $appointment->doctorProfile->user_id : null,
            ]);
        });
    }
}
