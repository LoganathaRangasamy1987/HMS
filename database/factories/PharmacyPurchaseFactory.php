<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\PharmacyPurchase;
use App\Models\PharmacySupplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PharmacyPurchase>
 */
class PharmacyPurchaseFactory extends Factory
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
            'pharmacy_supplier_id' => fn (array $attributes) => PharmacySupplier::factory()->create(['hospital_id' => $attributes['hospital_id']])->id,
            'number' => 'PUR-'.fake()->unique()->numerify('########'),
            'supplier_invoice_number' => 'INV-'.fake()->unique()->numerify('########'),
            'purchase_date' => now()->toDateString(),
            'status' => 'RECEIVED',
            'currency' => 'INR',
            'subtotal' => '100.00',
            'tax_amount' => '5.00',
            'total' => '105.00',
            'request_key' => (string) Str::uuid(),
            'payload_hash' => hash('sha256', fake()->uuid()),
            'received_by' => fn (array $attributes) => User::factory()->create(['hospital_id' => $attributes['hospital_id']])->id,
            'received_at' => now(),
        ];
    }
}
