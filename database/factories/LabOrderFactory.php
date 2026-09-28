<?php

namespace Database\Factories;

use App\Models\DoctorProfile;
use App\Models\Invoice;
use App\Models\LabOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabOrder>
 */
class LabOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'hospital_id' => fn (array $attributes): int => Invoice::findOrFail($attributes['invoice_id'])->hospital_id,
            'branch_id' => fn (array $attributes): int => Invoice::findOrFail($attributes['invoice_id'])->branch_id,
            'patient_id' => fn (array $attributes): int => Invoice::findOrFail($attributes['invoice_id'])->patient_id,
            'encounter_id' => null,
            'doctor_profile_id' => fn (array $attributes): int => DoctorProfile::factory()->create(['hospital_id' => $attributes['hospital_id'], 'branch_id' => $attributes['branch_id']])->id,
            'number' => fn (array $attributes): string => 'LAB-'.$attributes['hospital_id'].'-'.now()->year.'-'.fake()->unique()->numerify('######'),
            'status' => 'ORDERED',
            'clinical_notes' => fake()->optional()->sentence(),
            'ordered_by' => fn (array $attributes): int => Invoice::findOrFail($attributes['invoice_id'])->created_by,
            'ordered_at' => now(),
        ];
    }
}
