<?php

namespace Database\Factories;

use App\Models\Hospital;
use App\Models\MedicineType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicineType>
 */
class MedicineTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['hospital_id' => Hospital::factory(), 'name' => fake()->unique()->randomElement(['Tablet', 'Capsule', 'Syrup', 'Injection']).' '.fake()->unique()->numerify('##'), 'status' => 'active'];
    }
}
