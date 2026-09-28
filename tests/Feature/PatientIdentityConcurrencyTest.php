<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Hospital;
use App\Models\Patient;
use App\Services\PatientIdentityService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\SerializableClosure\SerializableClosure;
use PDO;
use PDOException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Throwable;

class PatientIdentityConcurrencyTest extends TestCase
{
    public function test_concurrent_registration_shares_one_hospital_sequence_across_branches(): void
    {
        if ((string) env('HMS_MYSQL_TEST', '0') !== '1') {
            $this->markTestSkipped('Set HMS_MYSQL_TEST=1 to run the isolated MySQL/MariaDB concurrency test.');
        }

        $this->assertTrue(app()->environment('testing'), 'The concurrency test requires APP_ENV=testing.');
        $this->assertContains('mysql', PDO::getAvailableDrivers(), 'The concurrency test requires pdo_mysql.');

        $schemaName = 'hms_identity_test_'.bin2hex(random_bytes(8));
        $this->assertMatchesRegularExpression('/\Ahms_identity_test_[a-f0-9]{16}\z/', $schemaName);
        $connectionName = 'patient_identity_test';
        $originalConnection = DB::getDefaultConnection();
        $connectionConfiguration = [
            'driver' => 'mysql',
            'url' => null,
            'host' => (string) env('HMS_MYSQL_HOST', '127.0.0.1'),
            'port' => (int) env('HMS_MYSQL_PORT', 3306),
            'database' => $schemaName,
            'username' => (string) env('HMS_MYSQL_USERNAME', 'root'),
            'password' => (string) env('HMS_MYSQL_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => 'InnoDB',
        ];

        $this->assertNotSame(config('database.connections.'.$originalConnection.'.database'), $schemaName);

        try {
            $administrator = new PDO(
                'mysql:host='.$connectionConfiguration['host'].';port='.$connectionConfiguration['port'].';charset=utf8mb4',
                $connectionConfiguration['username'],
                $connectionConfiguration['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
            );
        } catch (PDOException) {
            $this->fail('Cannot connect to the test database administrator. Check XAMPP MySQL and HMS_MYSQL_* settings.');
        }

        $createdSchema = false;

        try {
            $administrator->exec('CREATE DATABASE `'.$schemaName.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $createdSchema = true;

            config(['database.connections.'.$connectionName => $connectionConfiguration]);
            DB::setDefaultConnection($connectionName);
            $this->assertSame($schemaName, DB::selectOne('SELECT DATABASE() AS database_name')->database_name);
            $this->artisan('migrate', ['--database' => $connectionName, '--no-interaction' => true])->assertSuccessful();

            Schema::create('identity_test_barrier', function (Blueprint $table): void {
                $table->unsignedInteger('worker_number')->primary();
            });

            $hospital = Hospital::factory()->create();
            $branches = Branch::factory()->count(2)->for($hospital)->create();
            $this->assertSame(0, DB::table('patient_number_sequences')->count());

            $results = $this->runWorkers($connectionConfiguration, (int) $hospital->id, $branches->modelKeys());

            $this->assertCount(8, $results);
            $this->assertCount(8, array_unique(array_column($results, 'process_id')));
            $this->assertCount(8, array_unique(array_column($results, 'connection_id')));
            $this->assertCount(8, array_unique(array_column($results, 'patient_id')));
            $this->assertCount(8, array_unique(array_column($results, 'uhid')));
            $this->assertSame(8, Patient::query()->where('hospital_id', $hospital->id)->count());

            foreach ($branches as $branch) {
                $this->assertSame(4, Patient::query()->where('branch_id', $branch->id)->count());
            }

            $expectedIdentifiers = array_map(
                fn (int $number): string => sprintf('HSP-%d-2026-%06d', $hospital->id, $number),
                range(1, 8),
            );
            $this->assertEqualsCanonicalizing($expectedIdentifiers, array_column($results, 'uhid'));
            $this->assertSame(1, DB::table('patient_number_sequences')->count());
            $this->assertDatabaseHas('patient_number_sequences', [
                'hospital_id' => $hospital->id,
                'year' => 2026,
                'last_number' => 8,
            ]);
        } finally {
            DB::purge($connectionName);
            DB::setDefaultConnection($originalConnection);

            if ($createdSchema && preg_match('/\Ahms_identity_test_[a-f0-9]{16}\z/', $schemaName) === 1) {
                $administrator->exec('DROP DATABASE `'.$schemaName.'`');
            }
        }
    }

    /**
     * @param  array{driver: string, url: null, host: string, port: int, database: string, username: string, password: string, charset: string, collation: string, prefix: string, strict: bool, engine: string}  $connectionConfiguration
     * @param  array<int, int>  $branchIds
     * @return array<int, array{successful: bool, process_id: int, connection_id: int, patient_id: int, uhid: string}>
     */
    private function runWorkers(array $connectionConfiguration, int $hospitalId, array $branchIds): array
    {
        $processes = [];
        $results = [];

        try {
            for ($workerNumber = 0; $workerNumber < 8; $workerNumber++) {
                $branchId = $branchIds[$workerNumber % 2];
                $worker = static function () use ($connectionConfiguration, $hospitalId, $branchId, $workerNumber): array {
                    try {
                        if (! app()->environment('testing') || preg_match('/\Ahms_identity_test_[a-f0-9]{16}\z/', $connectionConfiguration['database']) !== 1) {
                            throw new RuntimeException('The concurrency worker requires an isolated testing database.');
                        }

                        config(['database.connections.patient_identity_test' => $connectionConfiguration]);
                        DB::setDefaultConnection('patient_identity_test');
                        DB::purge('patient_identity_test');

                        if (DB::selectOne('SELECT DATABASE() AS database_name')->database_name !== $connectionConfiguration['database']) {
                            throw new RuntimeException('The concurrency worker connected to an unexpected database.');
                        }

                        Date::setTestNow('2026-09-13 06:00:00');
                        DB::table('identity_test_barrier')->insert(['worker_number' => $workerNumber]);
                        $deadline = microtime(true) + 15;

                        while (DB::table('identity_test_barrier')->count() < 8) {
                            if (microtime(true) >= $deadline) {
                                throw new RuntimeException('The concurrency workers did not reach the ready barrier.');
                            }

                            usleep(10000);
                        }

                        $patient = app(PatientIdentityService::class)->create(
                            Hospital::query()->findOrFail($hospitalId),
                            Branch::query()->findOrFail($branchId),
                            [
                                'first_name' => 'Concurrent',
                                'last_name' => 'Fixture '.$workerNumber,
                                'date_of_birth' => '1990-01-01',
                                'gender' => 'other',
                                'mobile' => '9000000000',
                            ],
                        );

                        return [
                            'successful' => true,
                            'process_id' => getmypid(),
                            'connection_id' => (int) DB::selectOne('SELECT CONNECTION_ID() AS connection_id')->connection_id,
                            'patient_id' => (int) $patient->id,
                            'uhid' => $patient->uhid,
                        ];
                    } catch (Throwable $exception) {
                        return ['successful' => false, 'error_type' => $exception::class];
                    } finally {
                        DB::disconnect('patient_identity_test');
                    }
                };

                $process = new Process(
                    [PHP_BINARY, base_path('artisan'), 'invoke-serialized-closure', '--env=testing', '--no-interaction'],
                    base_path(),
                    [
                        'APP_ENV' => 'testing',
                        'DB_CONNECTION' => 'mysql',
                        'DB_HOST' => $connectionConfiguration['host'],
                        'DB_PORT' => (string) $connectionConfiguration['port'],
                        'DB_DATABASE' => $connectionConfiguration['database'],
                        'DB_USERNAME' => $connectionConfiguration['username'],
                        'DB_PASSWORD' => $connectionConfiguration['password'],
                        'DB_URL' => '',
                        'CACHE_STORE' => 'array',
                        'SESSION_DRIVER' => 'array',
                        'QUEUE_CONNECTION' => 'sync',
                        'MAIL_MAILER' => 'array',
                        'LARAVEL_INVOKABLE_CLOSURE' => base64_encode(serialize(new SerializableClosure($worker))),
                    ],
                    timeout: 30,
                );
                $processes[] = $process;
                $process->start();
            }

            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), 'A concurrent worker exited unsuccessfully.');
                $output = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                $this->assertTrue($output['successful'] ?? false, 'A concurrent worker could not execute its closure.');
                $result = unserialize($output['result'], ['allowed_classes' => false]);
                $this->assertTrue($result['successful'] ?? false, 'A concurrent registration failed: '.($result['error_type'] ?? 'unknown error'));
                $results[] = $result;
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
