<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\DoctorProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use LogicException;

class DoctorProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Fictional doctor profiles are only available in local/testing environments.');
        }
        $doctor = User::where('email', 'doctor@lotus.test')->first();
        $membership = $doctor?->memberships()->whereHas('branch', fn ($query) => $query->where('code', 'CBE'))->first();
        $department = $membership ? Department::where('branch_id', $membership->branch_id)->where('name', 'General Medicine')->first() : null;
        if ($doctor && $membership && $department) {
            DoctorProfile::firstOrCreate(['hospital_id' => $doctor->hospital_id, 'registration_number' => 'TNMC-DEMO-001'], ['branch_id' => $membership->branch_id, 'user_id' => $doctor->id, 'department_id' => $department->id, 'qualification' => 'MBBS, MD', 'specialization' => 'General Medicine', 'consultation_fee' => 500, 'status' => 'active']);
        }
    }
}
