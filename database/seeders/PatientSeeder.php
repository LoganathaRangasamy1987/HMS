<?php

namespace Database\Seeders;

use App\Models\Hospital;
use App\Models\Patient;
use App\Services\PatientIdentityService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class PatientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Fictional patients are only available in local/testing environments.');
        }

        foreach (['LOTUS' => 'CBE', 'RIVER' => 'SLM'] as $hospitalCode => $branchCode) {
            DB::transaction(function () use ($hospitalCode, $branchCode): void {
                $hospital = Hospital::query()->where('code', $hospitalCode)->where('status', 'active')->lockForUpdate()->first();
                $branch = $hospital?->branches()->where('code', $branchCode)->where('status', 'active')->first();
                if (! $branch) {
                    return;
                }

                $email = 'demo.patient@'.strtolower($hospitalCode).'.test';
                if (! Patient::query()->forHospital($hospital->id)->where('email', $email)->exists()) {
                    app(PatientIdentityService::class)->create($hospital, $branch, [
                        'first_name' => 'Demo', 'last_name' => 'Patient', 'date_of_birth' => '1990-01-01',
                        'gender' => 'unknown', 'email' => $email, 'mobile' => '9000000000',
                    ]);
                }
            }, attempts: 5);
        }
    }
}
