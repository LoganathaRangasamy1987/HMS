<?php

namespace Database\Factories;

use App\Models\Consultation;
use App\Models\ConsultationAmendment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsultationAmendment>
 */
class ConsultationAmendmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consultation_id' => Consultation::factory()->state(['status' => 'FINALIZED', 'finalized_at' => now()]),
            'chief_complaint' => fake()->sentence(),
            'history' => fake()->paragraph(),
            'examination' => fake()->sentence(),
            'clinical_notes' => fake()->paragraph(),
            'reason' => fake()->sentence(),
            'amended_by' => fn (array $attributes): int => Consultation::findOrFail($attributes['consultation_id'])->authored_by,
            'amended_at' => now(),
        ];
    }
}
