<?php

namespace Database\Factories;

use App\Models\LabOrderItem;
use App\Models\LabSpecimen;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabSpecimen>
 */
class LabSpecimenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lab_order_item_id' => LabOrderItem::factory(),
            'lab_order_id' => fn (array $attributes): int => LabOrderItem::findOrFail($attributes['lab_order_item_id'])->lab_order_id,
            'hospital_id' => fn (array $attributes): int => LabOrderItem::findOrFail($attributes['lab_order_item_id'])->order->hospital_id,
            'branch_id' => fn (array $attributes): int => LabOrderItem::findOrFail($attributes['lab_order_item_id'])->order->branch_id,
            'identifier' => fn (array $attributes): string => 'SPC-'.$attributes['hospital_id'].'-'.now()->year.'-'.fake()->unique()->numerify('#######'),
            'attempt' => 1,
            'status' => 'COLLECTED',
            'collected_by' => fn (array $attributes): int => LabOrderItem::findOrFail($attributes['lab_order_item_id'])->order->ordered_by,
            'collected_at' => now(),
        ];
    }
}
