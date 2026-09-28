<?php

namespace Database\Factories;

use App\Models\MedicineBatch;
use App\Models\PharmacyPurchase;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'medicine_batch_id' => MedicineBatch::factory(),
            'hospital_id' => fn (array $attributes) => MedicineBatch::findOrFail($attributes['medicine_batch_id'])->hospital_id,
            'branch_id' => fn (array $attributes) => MedicineBatch::findOrFail($attributes['medicine_batch_id'])->branch_id,
            'medicine_id' => fn (array $attributes) => MedicineBatch::findOrFail($attributes['medicine_batch_id'])->medicine_id,
            'type' => 'RECEIPT',
            'quantity' => '10.000',
            'balance_after' => '10.000',
            'reference_type' => PharmacyPurchase::class,
            'reference_id' => 1,
            'recorded_by' => fn (array $attributes) => User::factory()->create(['hospital_id' => $attributes['hospital_id']])->id,
            'recorded_at' => now(),
        ];
    }
}
