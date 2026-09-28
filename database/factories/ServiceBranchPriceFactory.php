<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\ServiceBranchPrice;
use App\Models\ServiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceBranchPrice>
 */
class ServiceBranchPriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_item_id' => ServiceItem::factory(),
            'branch_id' => fn (array $attributes) => Branch::factory()->create(['hospital_id' => ServiceItem::findOrFail($attributes['service_item_id'])->hospital_id])->id,
            'base_price' => null,
            'tax_rate_percent' => null,
            'discount_type' => null,
            'discount_value' => null,
            'is_available' => true,
        ];
    }
}
