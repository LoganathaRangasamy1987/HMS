<?php

namespace Database\Factories;

use App\Models\DoctorProfile;
use App\Models\DoctorScheduleException;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoctorScheduleException>
 */
class DoctorScheduleExceptionFactory extends Factory
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
            'date' => fake()->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d'),
            'availability' => 'available',
            'starts_at' => '10:00',
            'ends_at' => '12:00',
            'slot_duration_minutes' => 15,
            'capacity_per_slot' => 1,
            'reason' => fake()->sentence(),
        ];
    }
}
