<?php

namespace Database\Factories;

use App\Models\LabCategory;
use App\Models\LabOrder;
use App\Models\LabOrderItem;
use App\Models\LabSampleType;
use App\Models\LabTest;
use App\Models\LabTestVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabOrderItem>
 */
class LabOrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lab_order_id' => LabOrder::factory(),
            'lab_test_version_id' => function (array $attributes): int {
                $order = LabOrder::findOrFail($attributes['lab_order_id']);
                $test = LabTest::factory()->create(['hospital_id' => $order->hospital_id]);
                $category = LabCategory::factory()->create(['hospital_id' => $order->hospital_id]);
                $sampleType = LabSampleType::factory()->create(['hospital_id' => $order->hospital_id]);

                return LabTestVersion::factory()->create(['lab_test_id' => $test->id, 'category_id' => $category->id, 'sample_type_id' => $sampleType->id, 'created_by' => $order->ordered_by])->id;
            },
            'lab_test_id' => fn (array $attributes): int => LabTestVersion::findOrFail($attributes['lab_test_version_id'])->lab_test_id,
            'invoice_line_id' => null,
            'test_code' => strtoupper(fake()->unique()->bothify('LAB-####')),
            'test_name' => fake()->words(3, true),
            'version' => 1,
            'currency' => 'INR',
            'price' => fake()->randomFloat(2, 50, 2000),
            'status' => 'ORDERED',
        ];
    }
}
