<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class HospitalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Hospital',
            'code' => fake()->unique()->bothify('HSP-####??'),
            'city' => fake()->city(),
            'country' => 'India',
            'status' => 'active',
        ];
    }
}
