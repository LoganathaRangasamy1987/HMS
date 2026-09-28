<?php

namespace Database\Factories;

use App\Models\Diagnosis;
use App\Models\DiagnosisCorrection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiagnosisCorrection>
 */
class DiagnosisCorrectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'diagnosis_id' => Diagnosis::factory(),
            'type' => 'FINAL',
            'description' => fake()->sentence(),
            'code_system' => null,
            'code' => null,
            'reason' => fake()->sentence(),
            'corrected_by' => fn (array $attributes): int => Diagnosis::findOrFail($attributes['diagnosis_id'])->authored_by,
            'corrected_at' => now(),
        ];
    }
}
