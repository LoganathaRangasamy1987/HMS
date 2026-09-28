<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\DoctorProfile;
use App\Models\Hospital;
use App\Models\Membership;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Services\AppointmentService;
use App\Support\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Laravel\SerializableClosure\SerializableClosure;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Throwable;

class AppointmentConcurrencyTest extends TestCase
{
    public function test_simultaneous_bookings_respect_capacity_and_unique_tokens_on_mariadb(): void
    {
        if ((string) env('HMS_MYSQL_TEST', '0') !== '1') {
            $this->markTestSkipped('Set HMS_MYSQL_TEST=1 to run isolated MariaDB appointment concurrency.');
        }

        $this->assertTrue(app()->environment('testing'));
        $this->assertContains('mysql', PDO::getAvailableDrivers());
        $schema = 'hms_appointment_test_'.bin2hex(random_bytes(8));
        $this->assertMatchesRegularExpression('/\Ahms_appointment_test_[a-f0-9]{16}\z/', $schema);
        $configuration = [
            'driver' => 'mysql', 'url' => null,
            'host' => (string) env('HMS_MYSQL_HOST', '127.0.0.1'),
            'port' => (int) env('HMS_MYSQL_PORT', 3306),
            'database' => $schema,
            'username' => (string) env('HMS_MYSQL_USERNAME', 'root'),
            'password' => (string) env('HMS_MYSQL_PASSWORD', ''),
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '', 'strict' => true, 'engine' => 'InnoDB',
        ];
        $administrator = new PDO('mysql:host='.$configuration['host'].';port='.$configuration['port'].';charset=utf8mb4', $configuration['username'], $configuration['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        $originalConnection = DB::getDefaultConnection();
        $created = false;

        try {
            $administrator->exec('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $created = true;
            config(['database.connections.appointment_test' => $configuration]);
            DB::setDefaultConnection('appointment_test');
            $this->assertSame($schema, DB::selectOne('SELECT DATABASE() AS database_name')->database_name);
            $this->artisan('migrate', ['--database' => 'appointment_test', '--no-interaction' => true])->assertSuccessful();
            Schema::create('appointment_test_barrier', fn (Blueprint $table) => $table->unsignedInteger('worker_number')->primary());

            $hospital = Hospital::factory()->create();
            $branch = Branch::factory()->for($hospital)->create();
            $doctor = DoctorProfile::factory()->create(['hospital_id' => $hospital->id, 'branch_id' => $branch->id]);
            $doctor->schedules()->create(['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '10:00', 'slot_duration_minutes' => 15, 'capacity_per_slot' => 2, 'status' => 'active']);
            $patient = Patient::factory()->forBranch($branch)->create();
            $actor = User::factory()->create(['hospital_id' => $hospital->id]);
            $role = Role::create(['name' => 'RECEPTIONIST', 'label' => 'Receptionist']);
            $membership = Membership::create(['hospital_id' => $hospital->id, 'branch_id' => $branch->id, 'user_id' => $actor->id, 'role_id' => $role->id, 'status' => 'active']);
            $this->assertSame(0, Appointment::count());

            $results = $this->workers($configuration, (int) $hospital->id, (int) $branch->id, (int) $doctor->id, (int) $patient->id, (int) $actor->id, (int) $membership->id);
            $accepted = array_values(array_filter($results, fn (array $result): bool => $result['outcome'] === 'booked'));
            $rejected = array_values(array_filter($results, fn (array $result): bool => $result['outcome'] === 'full'));
            $this->assertCount(2, $accepted);
            $this->assertCount(6, $rejected);
            $this->assertEqualsCanonicalizing([1, 2], array_column($accepted, 'token'));
            $this->assertCount(8, array_unique(array_column($results, 'connection_id')));
            $this->assertSame(2, Appointment::count());
            $this->assertSame(2, Appointment::where('starts_at', '09:00')->count());
        } finally {
            DB::purge('appointment_test');
            DB::setDefaultConnection($originalConnection);
            if ($created && preg_match('/\Ahms_appointment_test_[a-f0-9]{16}\z/', $schema)) {
                $administrator->exec('DROP DATABASE `'.$schema.'`');
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private function workers(array $configuration, int $hospitalId, int $branchId, int $doctorId, int $patientId, int $actorId, int $membershipId): array
    {
        $processes = [];
        $results = [];

        try {
            for ($number = 0; $number < 8; $number++) {
                $worker = static function () use ($configuration, $hospitalId, $branchId, $doctorId, $patientId, $actorId, $membershipId, $number): array {
                    if (! app()->environment('testing') || preg_match('/\Ahms_appointment_test_[a-f0-9]{16}\z/', $configuration['database']) !== 1) {
                        throw new RuntimeException('Worker requires an isolated test database.');
                    }
                    config(['database.connections.appointment_test' => $configuration]);
                    DB::setDefaultConnection('appointment_test');
                    DB::purge('appointment_test');

                    try {
                        if (DB::selectOne('SELECT DATABASE() AS database_name')->database_name !== $configuration['database']) {
                            throw new RuntimeException('Worker connected to an unexpected database.');
                        }
                        DB::table('appointment_test_barrier')->insert(['worker_number' => $number]);
                        $deadline = microtime(true) + 20;
                        while (DB::table('appointment_test_barrier')->count() < 8) {
                            if (microtime(true) >= $deadline) {
                                throw new RuntimeException('Workers did not reach the barrier.');
                            }
                            usleep(10000);
                        }
                        Auth::loginUsingId($actorId);
                        app(TenantContext::class)->set(Membership::findOrFail($membershipId));
                        $appointment = app(AppointmentService::class)->book($hospitalId, $branchId, Patient::findOrFail($patientId), DoctorProfile::findOrFail($doctorId), User::findOrFail($actorId), [
                            'appointment_date' => '2026-10-05', 'starts_at' => '09:00', 'type' => 'NEW',
                            'request_key' => sprintf('550e8400-e29b-41d4-a716-%012d', $number),
                        ]);

                        return ['outcome' => 'booked', 'token' => $appointment->token_number, 'connection_id' => (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id];
                    } catch (ValidationException) {
                        return ['outcome' => 'full', 'connection_id' => (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id];
                    } catch (Throwable $exception) {
                        return ['outcome' => 'error', 'type' => $exception::class, 'connection_id' => (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id];
                    } finally {
                        DB::disconnect('appointment_test');
                    }
                };
                $process = new Process([PHP_BINARY, base_path('artisan'), 'invoke-serialized-closure', '--env=testing', '--no-interaction'], base_path(), [
                    'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql',
                    'DB_HOST' => $configuration['host'], 'DB_PORT' => (string) $configuration['port'],
                    'DB_DATABASE' => $configuration['database'], 'DB_USERNAME' => $configuration['username'],
                    'DB_PASSWORD' => $configuration['password'], 'DB_URL' => '',
                    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
                    'LARAVEL_INVOKABLE_CLOSURE' => base64_encode(serialize(new SerializableClosure($worker))),
                ], timeout: 40);
                $processes[] = $process;
                $process->start();
            }
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), 'Worker process failed: '.$process->getErrorOutput());
                $output = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                $this->assertTrue($output['successful'] ?? false, 'Worker invocation failed.');
                $results[] = unserialize($output['result'], ['allowed_classes' => false]);
            }
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(0);
                }
            }
        }

        return $results;
    }
}
