<?php

namespace Database\Factories;

use App\Models\Diagnosis;
use App\Models\Encounter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Diagnosis>
 */
class DiagnosisFactory extends Factory
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
            'type' => 'PROVISIONAL',
            'description' => fake()->sentence(),
            'code_system' => null,
            'code' => null,
            'diagnosed_at' => now(),
            'authored_by' => fn (array $attributes): int => Encounter::findOrFail($attributes['encounter_id'])->doctorProfile->user_id,
        ];
    }
}
