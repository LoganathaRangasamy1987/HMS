<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

#[Signature('hms:release-check {--strict : Enforce production environment, debug, mail, and queue settings}')]
#[Description('Verify database, migrations, cache, queue storage, writable paths, and production configuration.')]
class ReleaseCheck extends Command
{
    public function handle(): int
    {
        $checks = [];
        $this->check($checks, 'Application key', filled(config('app.key')), 'APP_KEY is configured');
        try {
            $started = hrtime(true);
            DB::select('SELECT 1');
            $latency = (hrtime(true) - $started) / 1_000_000;
            $this->check($checks, 'Database', $latency < 1000, sprintf('connected in %.2f ms', $latency));
        } catch (Throwable $exception) {
            $this->check($checks, 'Database', false, $exception->getMessage());
        }
        $migrationFiles = collect(app('migrator')->getMigrationFiles(database_path('migrations')))->keys()->map(fn (string $path): string => basename($path, '.php'));
        $ran = collect(app('migrator')->getRepository()->getRan());
        $pending = $migrationFiles->diff($ran);
        $this->check($checks, 'Migrations', $pending->isEmpty(), $pending->isEmpty() ? 'all migrations applied' : $pending->count().' pending');
        $this->check($checks, 'Queue tables', Schema::hasTable('jobs') && Schema::hasTable('failed_jobs'), 'jobs and failed_jobs tables');
        $this->check($checks, 'Writable storage', is_writable(storage_path('framework')) && is_writable(storage_path('logs')) && is_writable(base_path('bootstrap/cache')), 'framework, logs, and bootstrap cache');
        try {
            $key = 'release-check:'.bin2hex(random_bytes(8));
            Cache::put($key, 'ok', 10);
            $this->check($checks, 'Cache', Cache::pull($key) === 'ok', 'write/read/delete round trip');
        } catch (Throwable $exception) {
            $this->check($checks, 'Cache', false, $exception->getMessage());
        }
        if ($this->option('strict')) {
            $this->check($checks, 'Production environment', app()->environment('production'), 'APP_ENV=production');
            $this->check($checks, 'Debug disabled', ! config('app.debug'), 'APP_DEBUG=false');
            $this->check($checks, 'External mailer', config('mail.default') !== 'log', 'MAIL_MAILER must not be log');
            $this->check($checks, 'Asynchronous queue', ! in_array(config('queue.default'), ['sync', 'null'], true), 'queue must be asynchronous');
        }
        $this->table(['Check', 'Result', 'Detail'], $checks);

        return collect($checks)->every(fn (array $row): bool => $row[1] === 'PASS') ? self::SUCCESS : self::FAILURE;
    }

    /** @param list<array{string, string, string}> $checks */
    private function check(array &$checks, string $name, bool $passes, string $detail): void
    {
        $checks[] = [$name, $passes ? 'PASS' : 'FAIL', mb_substr($detail, 0, 160)];
    }
}
