<?php

namespace Database\Factories;

use App\Models\Hospital;
use App\Models\MedicineUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicineUnit>
 */
class MedicineUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['hospital_id' => Hospital::factory(), 'name' => fake()->unique()->word(), 'symbol' => strtoupper(fake()->unique()->lexify('???')), 'status' => 'active'];
    }
}
