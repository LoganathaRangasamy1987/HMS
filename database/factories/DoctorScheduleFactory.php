<?php

namespace Database\Factories;

use App\Models\DoctorProfile;
use App\Models\DoctorSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoctorSchedule>
 */
class DoctorScheduleFactory extends Factory
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
            'day_of_week' => fake()->numberBetween(1, 5),
            'starts_at' => '09:00',
            'ends_at' => '13:00',
            'slot_duration_minutes' => 15,
            'capacity_per_slot' => 1,
            'status' => 'active',
        ];
    }
}
