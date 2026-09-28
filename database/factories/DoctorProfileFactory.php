<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Department;
use App\Models\DoctorProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoctorProfile>
 */
class DoctorProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'hospital_id' => fn (array $attributes): int => Branch::findOrFail($attributes['branch_id'])->hospital_id,
            'user_id' => fn (array $attributes): int => User::factory()->create(['hospital_id' => $attributes['hospital_id']])->id,
            'department_id' => fn (array $attributes): int => Department::create(['hospital_id' => $attributes['hospital_id'], 'branch_id' => $attributes['branch_id'], 'name' => fake()->unique()->words(2, true), 'status' => 'active'])->id,
            'registration_number' => fake()->unique()->bothify('MED-#####'),
            'qualification' => 'MBBS',
            'specialization' => fake()->words(2, true),
            'consultation_fee' => fake()->randomFloat(2, 0, 5000),
            'status' => 'active',
        ];
    }
}
