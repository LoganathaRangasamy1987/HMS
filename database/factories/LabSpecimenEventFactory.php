<?php

namespace Database\Factories;

use App\Models\LabSpecimen;
use App\Models\LabSpecimenEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabSpecimenEvent>
 */
class LabSpecimenEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lab_specimen_id' => LabSpecimen::factory(),
            'from_status' => null,
            'to_status' => 'COLLECTED',
            'reason' => null,
            'recorded_by' => fn (array $attributes): int => LabSpecimen::findOrFail($attributes['lab_specimen_id'])->collected_by,
            'recorded_at' => now(),
        ];
    }
}
