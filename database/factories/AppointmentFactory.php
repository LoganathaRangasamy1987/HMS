<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hospital_id' => fn () => Patient::factory()->create()->hospital_id,
            'branch_id' => fn (array $attributes) => Patient::findOrFail($attributes['patient_id'])->branch_id,
            'patient_id' => Patient::factory(),
            'doctor_profile_id' => DoctorProfile::factory(),
            'appointment_date' => fake()->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d'),
            'starts_at' => '09:00', 'ends_at' => '09:15', 'token_number' => 1,
            'type' => 'NEW', 'status' => 'BOOKED', 'created_by' => User::factory(),
        ];
    }
}
