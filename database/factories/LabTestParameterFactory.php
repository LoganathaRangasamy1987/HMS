<?php

namespace Database\Factories;

use App\Models\LabTestParameter;
use App\Models\LabTestVersion;
use App\Models\LabUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabTestParameter>
 */
class LabTestParameterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['lab_test_version_id' => LabTestVersion::factory(), 'code' => strtoupper(fake()->unique()->bothify('PAR-####')), 'name' => fake()->words(2, true), 'result_type' => 'NUMERIC', 'unit_id' => LabUnit::factory(), 'reference_min' => '1.0000', 'reference_max' => '10.0000', 'reference_text' => null, 'sort_order' => 1];
    }
}
