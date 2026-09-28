<?php

namespace Database\Factories;

use App\Models\LabResult;
use App\Models\LabSpecimen;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabResult>
 */
class LabResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lab_specimen_id' => LabSpecimen::factory()->state(['status' => 'PROCESSING']),
            'lab_order_id' => fn (array $attributes): int => LabSpecimen::findOrFail($attributes['lab_specimen_id'])->lab_order_id,
            'lab_order_item_id' => fn (array $attributes): int => LabSpecimen::findOrFail($attributes['lab_specimen_id'])->lab_order_item_id,
            'hospital_id' => fn (array $attributes): int => LabSpecimen::findOrFail($attributes['lab_specimen_id'])->hospital_id,
            'branch_id' => fn (array $attributes): int => LabSpecimen::findOrFail($attributes['lab_specimen_id'])->branch_id,
            'revision' => 1,
            'status' => 'DRAFT',
            'entered_by' => fn (array $attributes): int => LabSpecimen::findOrFail($attributes['lab_specimen_id'])->collected_by,
            'entered_at' => now(),
        ];
    }
}
