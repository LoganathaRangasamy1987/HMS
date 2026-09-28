<?php

namespace Database\Factories;

use App\Models\Hospital;
use App\Models\PharmacyManufacturer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PharmacyManufacturer>
 */
class PharmacyManufacturerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['hospital_id' => Hospital::factory(), 'name' => fake()->unique()->company(), 'status' => 'active'];
    }
}
