<?php

namespace Database\Factories;

use App\Models\Encounter;
use App\Models\Prescription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prescription>
 */
class PrescriptionFactory extends Factory
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
            'notes' => fake()->optional()->sentence(),
            'prescribed_at' => now(),
            'prescribed_by' => fn (array $attributes): int => Encounter::findOrFail($attributes['encounter_id'])->doctorProfile->user_id,
        ];
    }
}
