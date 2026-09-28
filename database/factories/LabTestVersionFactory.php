<?php

namespace Database\Factories;

use App\Models\LabCategory;
use App\Models\LabSampleType;
use App\Models\LabTest;
use App\Models\LabTestVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabTestVersion>
 */
class LabTestVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['lab_test_id' => LabTest::factory(), 'version' => 1, 'category_id' => LabCategory::factory(), 'sample_type_id' => LabSampleType::factory(), 'sample_volume' => '2 mL', 'instructions' => fake()->sentence(), 'currency' => 'INR', 'price' => fake()->randomFloat(2, 50, 2000), 'status' => 'draft', 'created_by' => User::factory(), 'activated_at' => null];
    }
}
