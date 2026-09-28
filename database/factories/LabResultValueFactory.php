<?php

namespace Database\Factories;

use App\Models\LabResult;
use App\Models\LabResultValue;
use App\Models\LabTestParameter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabResultValue>
 */
class LabResultValueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lab_result_id' => LabResult::factory(),
            'lab_test_parameter_id' => fn (array $attributes): int => LabTestParameter::factory()->create(['lab_test_version_id' => LabResult::findOrFail($attributes['lab_result_id'])->orderItem->lab_test_version_id, 'result_type' => 'TEXT', 'unit_id' => null])->id,
            'parameter_code' => strtoupper(fake()->unique()->bothify('PAR-####')),
            'parameter_name' => fake()->words(2, true),
            'result_type' => 'TEXT',
            'unit_symbol' => null,
            'reference_min' => null,
            'reference_max' => null,
            'reference_text' => 'Expected value',
            'sort_order' => 1,
            'numeric_value' => null,
            'text_value' => fake()->word(),
            'boolean_value' => null,
            'flag' => 'NORMAL',
        ];
    }
}
