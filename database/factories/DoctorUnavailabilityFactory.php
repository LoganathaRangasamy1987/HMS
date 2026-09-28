<?php

namespace Database\Factories;

use App\Models\DoctorProfile;
use App\Models\DoctorUnavailability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoctorUnavailability>
 */
class DoctorUnavailabilityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'doctor_profile_id' => DoctorProfile::factory(),
            'type' => fake()->randomElement(['holiday', 'leave']),
            'starts_on' => fake()->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d'),
            'ends_on' => fn (array $attributes) => $attributes['starts_on'],
            'reason' => fake()->sentence(),
        ];
    }
}
