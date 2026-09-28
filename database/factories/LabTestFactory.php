<?php

namespace Database\Factories;

use App\Models\Hospital;
use App\Models\LabTest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabTest>
 */
class LabTestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['hospital_id' => Hospital::factory(), 'code' => strtoupper(fake()->unique()->bothify('LAB-####')), 'name' => fake()->words(3, true), 'status' => 'active', 'active_version_id' => null];
    }
}
