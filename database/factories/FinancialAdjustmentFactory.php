<?php

namespace Database\Factories;

use App\Models\FinancialAdjustment;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FinancialAdjustment>
 */
class FinancialAdjustmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'hospital_id' => fn (array $attributes): int => Invoice::findOrFail($attributes['invoice_id'])->hospital_id,
            'branch_id' => fn (array $attributes): int => Invoice::findOrFail($attributes['invoice_id'])->branch_id,
            'type' => 'CREDIT',
            'amount' => '1.00',
            'reason' => fake()->sentence(),
            'request_key' => fn (): string => (string) Str::uuid(),
            'recorded_at' => now(),
            'recorded_by' => fn (array $attributes): int => User::factory()->create(['hospital_id' => $attributes['hospital_id']])->id,
        ];
    }
}
