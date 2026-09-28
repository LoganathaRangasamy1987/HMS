<?php

namespace Database\Factories;

use App\Models\MedicineBatch;
use App\Models\PharmacyPurchase;
use App\Models\PharmacyPurchaseItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PharmacyPurchaseItem>
 */
class PharmacyPurchaseItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pharmacy_purchase_id' => PharmacyPurchase::factory(),
            'medicine_batch_id' => MedicineBatch::factory(),
            'medicine_id' => fn (array $attributes) => MedicineBatch::findOrFail($attributes['medicine_batch_id'])->medicine_id,
            'medicine_code' => strtoupper(fake()->bothify('MED-####')),
            'medicine_name' => fake()->words(2, true),
            'batch_number' => strtoupper(fake()->bothify('BAT-####')),
            'manufactured_on' => now()->subMonths(2)->toDateString(),
            'expires_on' => now()->addYear()->toDateString(),
            'unit_symbol' => 'PCS',
            'quantity' => '5.000',
            'free_quantity' => '0.000',
            'unit_cost' => '20.00',
            'sale_price' => '25.00',
            'tax_rate_percent' => '5.00',
            'subtotal' => '100.00',
            'tax_amount' => '5.00',
            'total' => '105.00',
        ];
    }
}
