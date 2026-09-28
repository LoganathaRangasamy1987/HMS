<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Hospital;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProvisionHospital extends Command
{
    protected $signature = 'hms:provision';

    protected $description = 'Provision a hospital, its first branch and administrator using interactive input';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Run interactively to enter the administrator password securely.');

            return self::FAILURE;
        }
        $data = [
            'hospital' => $this->ask('Hospital name'),
            'code' => strtoupper((string) $this->ask('Hospital code (letters, numbers, hyphens)')),
            'branch' => $this->ask('First branch name'),
            'branch_code' => strtoupper((string) $this->ask('Branch code')),
            'name' => $this->ask('Administrator name'),
            'email' => strtolower((string) $this->ask('Administrator email')),
            'password' => $this->secret('Administrator password (minimum 12 characters)'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];
        $validator = Validator::make($data, [
            'hospital' => ['required', 'string', 'max:150'], 'code' => ['required', 'regex:/^[A-Z0-9-]+$/', 'max:20', 'unique:hospitals,code'],
            'branch' => ['required', 'string', 'max:150'], 'branch_code' => ['required', 'regex:/^[A-Z0-9-]+$/', 'max:20'],
            'name' => ['required', 'string', 'max:150'], 'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'max:128', 'confirmed'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

return self::FAILURE;
        }
        DB::transaction(function () use ($data): void {
            (new PermissionsSeeder)->run();
            $hospital = Hospital::create(['name' => $data['hospital'], 'code' => $data['code'], 'status' => 'active']);
            $branch = Branch::create(['hospital_id' => $hospital->id, 'name' => $data['branch'], 'code' => $data['branch_code'], 'status' => 'active']);
            $user = User::create(['hospital_id' => $hospital->id, 'name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'status' => 'active']);
            Membership::create(['hospital_id' => $hospital->id, 'branch_id' => $branch->id, 'user_id' => $user->id, 'role_id' => Role::where('name', 'HOSPITAL_ADMIN')->firstOrFail()->id, 'status' => 'active']);
            AuditLog::create(['hospital_id' => $hospital->id, 'branch_id' => $branch->id, 'user_id' => $user->id, 'module' => 'setup', 'action' => 'hospital_provisioned', 'record_type' => Hospital::class, 'record_id' => $hospital->id, 'created_at' => now()]);
        });
        $this->info('Hospital and administrator created. Sign in through the portal.');

        return self::SUCCESS;
    }
}
