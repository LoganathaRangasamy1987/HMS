<?php

namespace Database\Factories;

use App\Models\Hospital;
use App\Models\PharmacySupplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PharmacySupplier>
 */
class PharmacySupplierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['hospital_id' => Hospital::factory(), 'code' => strtoupper(fake()->unique()->bothify('SUP-###')), 'name' => fake()->company(), 'contact_person' => fake()->name(), 'phone' => fake()->numerify('9#########'), 'email' => fake()->companyEmail(), 'status' => 'active'];
    }
}
