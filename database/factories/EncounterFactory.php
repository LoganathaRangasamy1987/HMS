<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Encounter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Encounter>
 */
class EncounterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory()->state(['status' => 'CONSULTING']),
            'hospital_id' => fn (array $attributes): int => Appointment::findOrFail($attributes['appointment_id'])->hospital_id,
            'branch_id' => fn (array $attributes): int => Appointment::findOrFail($attributes['appointment_id'])->branch_id,
            'patient_id' => fn (array $attributes): int => Appointment::findOrFail($attributes['appointment_id'])->patient_id,
            'doctor_profile_id' => fn (array $attributes): int => Appointment::findOrFail($attributes['appointment_id'])->doctor_profile_id,
            'department_id' => fn (array $attributes): int => Appointment::findOrFail($attributes['appointment_id'])->doctorProfile->department_id,
            'encounter_type' => 'OPD',
            'status' => 'ACTIVE',
            'opened_at' => now(),
            'opened_by' => fn (array $attributes): int => Appointment::findOrFail($attributes['appointment_id'])->doctorProfile->user_id,
        ];
    }
}
