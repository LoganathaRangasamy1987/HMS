<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\PatientMedicalHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientMedicalHistory>
 */
class PatientMedicalHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'hospital_id' => fn (array $attributes): int => Patient::findOrFail($attributes['patient_id'])->hospital_id,
            'branch_id' => fn (array $attributes): int => Patient::findOrFail($attributes['patient_id'])->branch_id,
            'recorded_by' => fn (array $attributes): int => User::factory()->create(['hospital_id' => $attributes['hospital_id']])->id,
            'condition' => fake()->words(2, true),
            'onset_date' => fake()->dateTimeBetween('-20 years', 'now')->format('Y-m-d'),
            'onset_date_unknown' => false,
            'status' => 'active',
        ];
    }
}
