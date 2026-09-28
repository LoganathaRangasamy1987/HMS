<?php

namespace Database\Factories;

use App\Models\Prescription;
use App\Models\PrescriptionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrescriptionItem>
 */
class PrescriptionItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prescription_id' => Prescription::factory(),
            'medicine_id' => null,
            'medicine_name' => 'Paracetamol',
            'strength' => '500 mg',
            'dose' => '1 tablet',
            'frequency' => 'Twice daily',
            'duration' => '3 days',
            'route' => 'Oral',
            'timing' => 'After food',
            'advice' => fake()->optional()->sentence(),
        ];
    }
}
