<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Hospital;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('Fictional demo data is only available in local/testing environments. Use PermissionsSeeder and hms:provision for deployment.');
        }
        $this->call(PermissionsSeeder::class);
        DB::transaction(function (): void {
            $lotus = Hospital::firstOrCreate(['code' => 'LOTUS'], ['name' => 'Lotus Care Hospital', 'registration_number' => 'DEMO-LOTUS-001', 'email' => 'hello@lotus.test', 'phone' => '0422 555 0100', 'address' => '12 Garden Road', 'city' => 'Coimbatore', 'state' => 'Tamil Nadu', 'country' => 'India', 'status' => 'active']);
            $river = Hospital::firstOrCreate(['code' => 'RIVER'], ['name' => 'Riverbank Hospital', 'email' => 'hello@river.test', 'city' => 'Salem', 'state' => 'Tamil Nadu', 'country' => 'India', 'status' => 'active']);
            $cbe = Branch::firstOrCreate(['hospital_id' => $lotus->id, 'code' => 'CBE'], ['name' => 'Coimbatore Central', 'city' => 'Coimbatore', 'address' => '12 Garden Road', 'email' => 'central@lotus.test', 'phone' => '0422 555 0100', 'status' => 'active']);
            $chn = Branch::firstOrCreate(['hospital_id' => $lotus->id, 'code' => 'CHN'], ['name' => 'Chennai Clinic', 'city' => 'Chennai', 'email' => 'chennai@lotus.test', 'status' => 'active']);
            $slm = Branch::firstOrCreate(['hospital_id' => $river->id, 'code' => 'SLM'], ['name' => 'Salem Main', 'city' => 'Salem', 'status' => 'active']);
            foreach ([[$cbe, 'General Medicine'], [$cbe, 'Cardiology'], [$cbe, 'Paediatrics'], [$chn, 'General Medicine'], [$chn, 'Orthopaedics'], [$slm, 'General Medicine']] as [$branch, $name]) {
                Department::firstOrCreate(['branch_id' => $branch->id, 'name' => $name], ['hospital_id' => $branch->hospital_id, 'description' => 'Fictional demonstration department.', 'status' => 'active']);
            }
            foreach ([['admin@lotus.test', 'Ananya Raman', $lotus, [$cbe, $chn], 'HOSPITAL_ADMIN'], ['reception@lotus.test', 'Priya S', $lotus, [$cbe], 'RECEPTIONIST'], ['doctor@lotus.test', 'Dr Arjun Kumar', $lotus, [$cbe], 'DOCTOR'], ['admin@river.test', 'Riverbank Administrator', $river, [$slm], 'HOSPITAL_ADMIN']] as [$email, $name, $hospital, $branches, $role]) {
                $user = User::firstOrCreate(['email' => $email], ['hospital_id' => $hospital->id, 'name' => $name, 'password' => 'CareDesk@2026!', 'status' => 'active']);
                foreach ($branches as $branch) {
                    Membership::firstOrCreate(['user_id' => $user->id, 'branch_id' => $branch->id], ['hospital_id' => $hospital->id, 'role_id' => Role::where('name', $role)->firstOrFail()->id, 'status' => 'active']);
                }
                if ($user->wasRecentlyCreated) {
                    AuditLog::create(['hospital_id' => $hospital->id, 'branch_id' => $branches[0]->id, 'user_id' => $user->id, 'module' => 'setup', 'action' => 'demo_account_created', 'record_type' => User::class, 'record_id' => $user->id, 'created_at' => now()]);
                }
            }
        });
        $this->call(PatientSeeder::class);
        if (! app()->runningUnitTests()) {
            $this->call(PatientClinicalHistorySeeder::class);
            $this->call(DoctorProfileSeeder::class);
            $this->call(DoctorAvailabilitySeeder::class);
            $this->call(AppointmentSeeder::class);
            $this->call(ServiceCatalogSeeder::class);
        }
        $this->call(MedicineSeeder::class);
    }
}
