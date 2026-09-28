<?php

namespace Database\Factories;

use App\Models\Encounter;
use App\Models\VitalObservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VitalObservation>
 */
class VitalObservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'encounter_id' => Encounter::factory(),
            'hospital_id' => fn (array $attributes): int => Encounter::findOrFail($attributes['encounter_id'])->hospital_id,
            'branch_id' => fn (array $attributes): int => Encounter::findOrFail($attributes['encounter_id'])->branch_id,
            'patient_id' => fn (array $attributes): int => Encounter::findOrFail($attributes['encounter_id'])->patient_id,
            'temperature' => '37.00',
            'pulse' => 78,
            'respiratory_rate' => 18,
            'systolic_bp' => 120,
            'diastolic_bp' => 80,
            'oxygen_saturation' => '98.00',
            'measured_at' => now(),
            'recorded_by' => fn (array $attributes): int => Encounter::findOrFail($attributes['encounter_id'])->doctorProfile->user_id,
        ];
    }
}
