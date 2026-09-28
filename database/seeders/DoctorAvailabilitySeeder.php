<?php

namespace Database\Seeders;

use App\Models\DoctorProfile;
use Illuminate\Database\Seeder;
use LogicException;

class DoctorAvailabilitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Fictional doctor availability is only available in local/testing environments.');
        }

        $profile = DoctorProfile::where('registration_number', 'TNMC-DEMO-001')->first();
        if (! $profile) {
            return;
        }

        foreach ([1, 2, 3, 4, 5] as $dayOfWeek) {
            $profile->schedules()->updateOrCreate(
                ['day_of_week' => $dayOfWeek, 'starts_at' => '09:00'],
                ['ends_at' => '13:00', 'slot_duration_minutes' => 15, 'capacity_per_slot' => 1, 'status' => 'active'],
            );
        }
    }
}
