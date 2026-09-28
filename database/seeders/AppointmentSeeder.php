<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\Patient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use LogicException;

class AppointmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Fictional appointments are only available in local/testing environments.');
        }
        $doctor = DoctorProfile::where('registration_number', 'TNMC-DEMO-001')->with('branch')->first();
        $patient = Patient::where('email', 'demo.patient@lotus.test')->first();
        $creator = User::where('email', 'reception@lotus.test')->first();
        if (! $doctor || ! $patient || ! $creator) {
            return;
        }
        $date = CarbonImmutable::now($doctor->branch->timezone)->next(CarbonImmutable::MONDAY)->toDateString();
        Appointment::firstOrCreate(
            ['hospital_id' => $doctor->hospital_id, 'request_key' => '550e8400-e29b-41d4-a716-446655449001'],
            ['branch_id' => $doctor->branch_id, 'patient_id' => $patient->id, 'doctor_profile_id' => $doctor->id, 'appointment_date' => $date, 'starts_at' => '09:00', 'ends_at' => '09:15', 'token_number' => 1, 'type' => 'NEW', 'status' => 'BOOKED', 'reason' => 'Fictional demonstration appointment.', 'created_by' => $creator->id],
        );
    }
}
