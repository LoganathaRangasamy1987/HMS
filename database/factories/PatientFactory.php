<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
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
            'hospital_id' => fn (array $attributes): int => Branch::query()->findOrFail($attributes['branch_id'])->hospital_id,
            'uhid' => 'TEST-'.Str::uuid(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->dateTimeBetween('-80 years', '-1 year')->format('Y-m-d'),
            'date_of_birth_unknown' => false,
            'gender' => 'unknown',
            'mobile' => '9000000000',
            'email' => fake()->unique()->safeEmail(),
            'status' => 'active',
        ];
    }

    public function forBranch(Branch $branch): static
    {
        return $this->state(fn (): array => ['hospital_id' => $branch->hospital_id, 'branch_id' => $branch->id]);
    }
}
