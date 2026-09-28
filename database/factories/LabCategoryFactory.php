<?php

namespace Database\Factories;

use App\Models\Hospital;
use App\Models\LabCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabCategory>
 */
class LabCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['hospital_id' => Hospital::factory(), 'code' => strtoupper(fake()->unique()->bothify('CAT-####')), 'name' => fake()->words(2, true), 'status' => 'active'];
    }
}
