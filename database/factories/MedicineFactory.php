<?php

namespace Database\Factories;

use App\Models\Hospital;
use App\Models\Medicine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Medicine>
 */
class MedicineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hospital_id' => Hospital::factory(),
            'code' => strtoupper(fake()->unique()->bothify('MED-####')),
            'name' => fake()->words(2, true),
            'generic_name' => fake()->word(),
            'form' => fake()->randomElement(['Tablet', 'Capsule', 'Syrup']),
            'strength' => fake()->randomElement(['250 mg', '500 mg', '5 mg/ml']),
            'status' => 'active',
        ];
    }
}
