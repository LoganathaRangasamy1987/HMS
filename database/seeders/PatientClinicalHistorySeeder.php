<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Seeder;
use LogicException;

class PatientClinicalHistorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Fictional clinical history is only available in local/testing environments.');
        }
        $patient = Patient::where('email', 'demo.patient@lotus.test')->first();
        $doctor = User::where('email', 'doctor@lotus.test')->first();
        if (! $patient || ! $doctor) {
            return;
        }
        $patient->allergies()->firstOrCreate(['allergen' => 'Demo pollen allergy'], ['hospital_id' => $patient->hospital_id, 'branch_id' => $patient->branch_id, 'recorded_by' => $doctor->id, 'reaction' => 'Fictional seasonal symptoms', 'severity' => 'mild', 'status' => 'active']);
        $patient->medicalHistories()->firstOrCreate(['condition' => 'Demo prior condition'], ['hospital_id' => $patient->hospital_id, 'branch_id' => $patient->branch_id, 'recorded_by' => $doctor->id, 'onset_date_unknown' => true, 'status' => 'resolved', 'notes' => 'Fictional demonstration record.']);
    }
}
