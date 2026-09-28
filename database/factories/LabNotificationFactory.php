<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\LabNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabNotification>
 */
class LabNotificationFactory extends Factory
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
            'hospital_id' => fn (array $attributes): int => Branch::findOrFail($attributes['branch_id'])->hospital_id,
            'recipient_user_id' => fn (array $attributes): int => User::factory()->create(['hospital_id' => $attributes['hospital_id']])->id,
            'event_key' => fake()->unique()->uuid(),
            'type' => 'ORDER_CREATED',
            'title' => 'Laboratory update',
            'message' => fake()->sentence(),
            'url' => '/laboratory/worklist',
            'read_at' => null,
        ];
    }
}
