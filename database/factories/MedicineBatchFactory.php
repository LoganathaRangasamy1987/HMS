<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicineBatch>
 */
class MedicineBatchFactory extends Factory
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
            'hospital_id' => fn (array $attributes) => Branch::findOrFail($attributes['branch_id'])->hospital_id,
            'medicine_id' => fn (array $attributes) => Medicine::factory()->create(['hospital_id' => $attributes['hospital_id']])->id,
            'batch_number' => strtoupper(fake()->unique()->bothify('BAT-####')),
            'manufactured_on' => now()->subMonths(2)->toDateString(),
            'expires_on' => now()->addYear()->toDateString(),
            'received_quantity' => '10.000',
            'on_hand_quantity' => '10.000',
            'unit_cost' => '20.00',
            'sale_price' => '25.00',
            'status' => 'active',
        ];
    }
}
