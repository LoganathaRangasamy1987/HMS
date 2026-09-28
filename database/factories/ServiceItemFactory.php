<?php

namespace Database\Factories;

use App\Models\Hospital;
use App\Models\ServiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceItem>
 */
class ServiceItemFactory extends Factory
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
            'code' => strtoupper(fake()->unique()->bothify('SVC-####')),
            'name' => fake()->words(3, true),
            'type' => 'SERVICE',
            'description' => fake()->sentence(),
            'currency' => 'INR',
            'base_price' => '500.00',
            'tax_rate_percent' => '0.00',
            'discount_type' => 'none',
            'discount_value' => '0.00',
            'status' => 'active',
        ];
    }
}
