<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
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
            'patient_id' => fn (array $attributes): int => Patient::factory()->forBranch(Branch::findOrFail($attributes['branch_id']))->create()->id,
            'created_by' => fn (array $attributes): int => User::factory()->create(['hospital_id' => $attributes['hospital_id']])->id,
            'status' => 'DRAFT',
        ];
    }
}
