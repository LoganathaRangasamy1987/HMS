<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory()->state(fn (): array => ['status' => 'ISSUED', 'number' => 'TEST-'.Str::uuid(), 'total' => '10.00', 'issued_at' => now()]),
            'hospital_id' => fn (array $attributes): int => Invoice::findOrFail($attributes['invoice_id'])->hospital_id,
            'branch_id' => fn (array $attributes): int => Invoice::findOrFail($attributes['invoice_id'])->branch_id,
            'amount' => '10.00',
            'mode' => 'CASH',
            'request_key' => fn (): string => (string) Str::uuid(),
            'received_at' => now(),
            'received_by' => fn (array $attributes): int => User::factory()->create(['hospital_id' => $attributes['hospital_id']])->id,
        ];
    }
}
