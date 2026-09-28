<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Hospital;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\PaymentService;
use App\Support\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\SerializableClosure\SerializableClosure;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Throwable;

class PaymentConcurrencyTest extends TestCase
{
    public function test_simultaneous_duplicate_payment_requests_create_one_entry_on_mariadb(): void
    {
        if ((string) env('HMS_MYSQL_TEST', '0') !== '1') {
            $this->markTestSkipped('Set HMS_MYSQL_TEST=1 to run isolated MariaDB payment concurrency.');
        }

        $this->assertTrue(app()->environment('testing'));
        $this->assertContains('mysql', PDO::getAvailableDrivers());
        $schema = 'hms_payment_test_'.bin2hex(random_bytes(8));
        $this->assertMatchesRegularExpression('/\Ahms_payment_test_[a-f0-9]{16}\z/', $schema);
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
            config(['database.connections.payment_test' => $configuration]);
            DB::setDefaultConnection('payment_test');
            $this->assertSame($schema, DB::selectOne('SELECT DATABASE() AS database_name')->database_name);
            $this->artisan('migrate', ['--database' => 'payment_test', '--no-interaction' => true])->assertSuccessful();
            Schema::create('payment_test_barrier', fn (Blueprint $table) => $table->unsignedInteger('worker_number')->primary());

            $hospital = Hospital::factory()->create();
            $branch = Branch::factory()->for($hospital)->create();
            $patient = Patient::factory()->forBranch($branch)->create();
            $actor = User::factory()->create(['hospital_id' => $hospital->id]);
            $role = Role::create(['name' => 'HOSPITAL_ADMIN', 'label' => 'Administrator']);
            $membership = Membership::create(['hospital_id' => $hospital->id, 'branch_id' => $branch->id, 'user_id' => $actor->id, 'role_id' => $role->id, 'status' => 'active']);
            $invoice = Invoice::factory()->create([
                'hospital_id' => $hospital->id,
                'branch_id' => $branch->id,
                'patient_id' => $patient->id,
                'created_by' => $actor->id,
                'status' => 'ISSUED',
                'number' => 'CONCURRENT-PAYMENT',
                'total' => '100.00',
                'issued_by' => $actor->id,
                'issued_at' => now(),
            ]);
            $requestKey = '550e8400-e29b-41d4-a716-446655440000';

            $results = $this->workers($configuration, $invoice->id, $actor->id, $membership->id, $requestKey);
            $this->assertCount(1, array_filter($results, fn (array $result): bool => $result['replayed'] === false));
            $this->assertCount(3, array_filter($results, fn (array $result): bool => $result['replayed'] === true));
            $this->assertCount(4, array_unique(array_column($results, 'connection_id')));
            $this->assertSame(1, Payment::count());
            $this->assertSame('100.00', Payment::firstOrFail()->amount);
            $this->assertSame('PAID', $invoice->fresh()->status);
        } finally {
            DB::purge('payment_test');
            DB::setDefaultConnection($originalConnection);
            if ($created && preg_match('/\Ahms_payment_test_[a-f0-9]{16}\z/', $schema)) {
                $administrator->exec('DROP DATABASE `'.$schema.'`');
            }
        }
    }

    /** @return list<array{replayed: bool, connection_id: int}> */
    private function workers(array $configuration, int $invoiceId, int $actorId, int $membershipId, string $requestKey): array
    {
        $processes = [];
        $results = [];

        try {
            for ($number = 0; $number < 4; $number++) {
                $worker = static function () use ($configuration, $invoiceId, $actorId, $membershipId, $requestKey, $number): array {
                    if (! app()->environment('testing') || preg_match('/\Ahms_payment_test_[a-f0-9]{16}\z/', $configuration['database']) !== 1) {
                        throw new RuntimeException('Worker requires an isolated test database.');
                    }
                    config(['database.connections.payment_test' => $configuration]);
                    DB::setDefaultConnection('payment_test');
                    DB::purge('payment_test');

                    try {
                        if (DB::selectOne('SELECT DATABASE() AS database_name')->database_name !== $configuration['database']) {
                            throw new RuntimeException('Worker connected to an unexpected database.');
                        }
                        DB::table('payment_test_barrier')->insert(['worker_number' => $number]);
                        $deadline = microtime(true) + 20;
                        while (DB::table('payment_test_barrier')->count() < 4) {
                            if (microtime(true) >= $deadline) {
                                throw new RuntimeException('Workers did not reach the barrier.');
                            }
                            usleep(10000);
                        }
                        Auth::loginUsingId($actorId);
                        app(TenantContext::class)->set(Membership::findOrFail($membershipId));
                        $result = app(PaymentService::class)->collect(Invoice::findOrFail($invoiceId), [
                            'amount' => '100.00',
                            'mode' => 'CASH',
                            'reference' => null,
                            'request_key' => $requestKey,
                        ]);

                        return ['replayed' => $result['replayed'], 'connection_id' => (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id];
                    } catch (Throwable $exception) {
                        throw new RuntimeException($exception::class.': '.$exception->getMessage(), previous: $exception);
                    } finally {
                        DB::disconnect('payment_test');
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
