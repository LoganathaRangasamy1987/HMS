<?php

namespace Database\Factories;

use App\Models\Hospital;
use App\Models\LabUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabUnit>
 */
class LabUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['hospital_id' => Hospital::factory(), 'code' => strtoupper(fake()->unique()->bothify('UNT-####')), 'name' => fake()->randomElement(['Milligrams per decilitre', 'Grams per decilitre', 'International units']), 'symbol' => fake()->randomElement(['mg/dL', 'g/dL', 'IU/L']), 'status' => 'active'];
    }
}
