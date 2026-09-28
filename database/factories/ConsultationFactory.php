<?php

namespace Database\Factories;

use App\Models\Consultation;
use App\Models\Encounter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consultation>
 */
class ConsultationFactory extends Factory
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
            'chief_complaint' => fake()->sentence(),
            'history' => fake()->paragraph(),
            'examination' => fake()->sentence(),
            'clinical_notes' => fake()->paragraph(),
            'status' => 'DRAFT',
            'authored_by' => fn (array $attributes): int => Encounter::findOrFail($attributes['encounter_id'])->doctorProfile->user_id,
        ];
    }
}
