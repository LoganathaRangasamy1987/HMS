<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

#[Signature('hms:backup-sqlite {--name= : Backup filename ending in .sqlite}')]
#[Description('Create a consistent SQLite backup in protected application storage without overwriting an existing file.')]
class BackupSqlite extends Command
{
    public function handle(): int
    {
        if (DB::getDriverName() !== 'sqlite' || DB::getDatabaseName() === ':memory:') {
            $this->error('This command supports persistent SQLite databases only.');

            return self::FAILURE;
        }
        $name = (string) ($this->option('name') ?: 'hms-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(3)).'.sqlite');
        if (! preg_match('/\A[a-zA-Z0-9._-]+\.sqlite\z/', $name) || basename($name) !== $name) {
            $this->error('The backup name must be a simple filename ending in .sqlite.');

            return self::FAILURE;
        }
        $directory = storage_path('app/backups');
        File::ensureDirectoryExists($directory, 0700);
        $target = $directory.DIRECTORY_SEPARATOR.$name;
        if (File::exists($target)) {
            $this->error('Refusing to overwrite an existing backup.');

            return self::FAILURE;
        }
        try {
            DB::statement('VACUUM INTO '.DB::connection()->getPdo()->quote($target));
            @chmod($target, 0600);
        } catch (Throwable $exception) {
            File::delete($target);
            $this->error('Backup failed: '.$exception->getMessage());

            return self::FAILURE;
        }
        $this->info('Backup created: '.$target);
        $this->line('Size: '.File::size($target).' bytes');
        $this->line('SHA-256: '.hash_file('sha256', $target));

        return self::SUCCESS;
    }
}
