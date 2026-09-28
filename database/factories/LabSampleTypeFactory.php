<?php

namespace Database\Factories;

use App\Models\Hospital;
use App\Models\LabSampleType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabSampleType>
 */
class LabSampleTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['hospital_id' => Hospital::factory(), 'code' => strtoupper(fake()->unique()->bothify('SMP-####')), 'name' => fake()->randomElement(['Serum', 'Plasma', 'Whole blood', 'Urine']), 'status' => 'active'];
    }
}
