<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use PDO;
use Throwable;

#[Signature('hms:verify-sqlite-backup {name : Backup filename from storage/app/backups}')]
#[Description('Restore a SQLite backup to an isolated temporary file and verify integrity and representative record counts.')]
class VerifySqliteBackup extends Command
{
    public function handle(): int
    {
        $name = (string) $this->argument('name');
        if (! preg_match('/\A[a-zA-Z0-9._-]+\.sqlite\z/', $name) || basename($name) !== $name) {
            $this->error('The backup name must be a simple filename ending in .sqlite.');

            return self::FAILURE;
        }
        $source = storage_path('app/backups'.DIRECTORY_SEPARATOR.$name);
        if (! File::isFile($source)) {
            $this->error('Backup not found in protected application storage.');

            return self::FAILURE;
        }
        $temporary = tempnam(sys_get_temp_dir(), 'hms-restore-');
        if ($temporary === false) {
            $this->error('Unable to allocate an isolated restore file.');

            return self::FAILURE;
        }
        try {
            if (! File::copy($source, $temporary)) {
                throw new \RuntimeException('Unable to copy the backup into the isolated restore file.');
            }
            $pdo = new PDO('sqlite:'.$temporary, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $integrity = $pdo->query('PRAGMA integrity_check')->fetchColumn();
            if ($integrity !== 'ok') {
                throw new \RuntimeException('SQLite integrity check failed.');
            }
            $required = ['migrations', 'hospitals', 'branches', 'users', 'patients', 'invoices', 'lab_orders', 'medicine_batches', 'operational_notifications'];
            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
            $missing = array_diff($required, $tables);
            if ($missing !== []) {
                throw new \RuntimeException('Required tables missing: '.implode(', ', $missing));
            }
            $rows = [];
            foreach (['migrations', 'hospitals', 'users', 'patients', 'invoices'] as $table) {
                $rows[] = [$table, (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn()];
            }
            $this->table(['Restored table', 'Rows'], $rows);
            $this->info('Isolated restore and integrity verification passed.');
            $this->line('Verified SHA-256: '.hash_file('sha256', $source));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Restore verification failed: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            File::delete($temporary);
        }
    }
}
