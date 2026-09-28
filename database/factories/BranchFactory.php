<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Hospital;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
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
            'name' => fake()->city().' Branch',
            'code' => fake()->unique()->bothify('BR-####??'),
            'city' => fake()->city(),
            'status' => 'active',
        ];
    }
}
