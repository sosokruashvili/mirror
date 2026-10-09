<?php

namespace App\Console\Commands;

use App\Support\DevEnvironment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Replace the DEVELOPMENT database with a fresh copy of PRODUCTION.
 *
 * Source: the production connection (`database.prod_connection`, i.e. the
 * app's normal DB_* settings). Target: the dev connection
 * (`database.dev_connection` → DB_DEV_DATABASE). Both live on the same
 * PostgreSQL server under the same role and differ only by database name.
 *
 * Steps: create the target DB if missing (no backup then) → otherwise back it
 * up to storage/app/db-backups/ → pg_dump prod (custom format) → drop and
 * recreate the dev `public` schema → pg_restore --no-owner --no-acl.
 *
 * SAFETY: the command never writes to the source. It refuses when source and
 * target database names are equal, or when they are not on the same server.
 * It is reached from the Settings page (SettingController@syncFromProd, only
 * for a developer currently on the dev DB) and from the CLI.
 */
class SyncDbFromProd extends Command
{
    protected $signature = 'db:sync-from-prod
                            {--force : Skip the interactive confirmation}';

    protected $description = 'Copy the production database into the development database, overwriting it';

    /** Seconds allowed for each pg_dump / pg_restore / psql step. */
    private const PROCESS_TIMEOUT = 1800;

    public function handle(): int
    {
        $sourceName = DevEnvironment::prodConnection();
        $targetName = DevEnvironment::devConnection();
        $source = (array) config("database.connections.{$sourceName}", []);
        $target = (array) config("database.connections.{$targetName}", []);

        // --- Guards --------------------------------------------------------
        if (($source['driver'] ?? null) !== 'pgsql' || ($target['driver'] ?? null) !== 'pgsql') {
            $this->error('This command only supports pgsql connections.');

            return self::FAILURE;
        }

        if (empty($source['database']) || empty($target['database'])) {
            $this->error('Source (DB_DATABASE) or target (DB_DEV_DATABASE) database name is not configured.');

            return self::FAILURE;
        }

        if ($source['database'] === $target['database']) {
            $this->error(sprintf(
                'Refusing to run: source and target are the same database ("%s"). Set DB_DEV_DATABASE to a different name.',
                $target['database']
            ));

            return self::FAILURE;
        }

        if (($source['host'] ?? null) !== ($target['host'] ?? null)
            || (string) ($source['port'] ?? 5432) !== (string) ($target['port'] ?? 5432)) {
            $this->error('Refusing to run: source and target must be on the same database server.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(sprintf(
            'This will ERASE "%s" and replace it with a copy of "%s". Continue?',
            $target['database'],
            $source['database']
        ))) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        // --- Tooling -------------------------------------------------------
        try {
            $bin = $this->clientBinaries($sourceName);
        } catch (\Throwable $e) {
            $this->error('Cannot determine PostgreSQL client binaries: '.$e->getMessage());

            return self::FAILURE;
        }

        $backupDir = storage_path('app/db-backups');
        if (! is_dir($backupDir) && ! @mkdir($backupDir, 02775, true) && ! is_dir($backupDir)) {
            $this->error("Cannot create backup directory {$backupDir}.");

            return self::FAILURE;
        }
        if (! is_writable($backupDir)) {
            $this->error("Backup directory {$backupDir} is not writable by ".(get_current_user() ?: 'the current user').'.');

            return self::FAILURE;
        }

        $stamp = now()->format('Ymd_His');
        $prodDump = $backupDir.DIRECTORY_SEPARATOR.".prod-{$source['database']}-{$stamp}.dump";
        $devBackup = $backupDir.DIRECTORY_SEPARATOR."{$target['database']}-{$stamp}.dump";
        $connArgs = fn (array $c) => [
            '--host='.$c['host'],
            '--port='.($c['port'] ?? 5432),
            '--username='.$c['username'],
            '--no-password',
        ];

        try {
            // 1. Target database: create when missing, otherwise back it up.
            $exists = (bool) DB::connection($sourceName)
                ->selectOne('select 1 as ok from pg_database where datname = ?', [$target['database']]);

            if (! $exists) {
                $this->line(sprintf('1/4 Target database "%s" does not exist — creating it (no backup needed).', $target['database']));
                DB::connection($sourceName)->statement(sprintf(
                    'CREATE DATABASE %s OWNER %s',
                    $this->quoteIdent($target['database']),
                    $this->quoteIdent($target['username'])
                ));
            } else {
                $this->line(sprintf('1/4 Backing up current "%s" to %s…', $target['database'], $devBackup));
                $this->runProcess([
                    $bin['pg_dump'], ...$connArgs($target),
                    '--format=custom', '--no-owner', '--no-acl',
                    '--file='.$devBackup,
                    $target['database'],
                ], $target['password'] ?? '');
            }

            // 2. Dump production.
            $this->line(sprintf('2/4 Dumping production "%s"…', $source['database']));
            $this->runProcess([
                $bin['pg_dump'], ...$connArgs($source),
                '--format=custom', '--no-owner', '--no-acl',
                '--file='.$prodDump,
                $source['database'],
            ], $source['password'] ?? '');

            // 3. Reset the dev schema (drop our own connection to it first).
            $this->line('3/4 Resetting the development schema…');
            DB::purge($targetName);
            $this->runProcess([
                $bin['psql'], ...$connArgs($target),
                '--dbname='.$target['database'],
                '--set=ON_ERROR_STOP=1', '--quiet',
                '--command=DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;',
            ], $target['password'] ?? '');

            // 4. Restore production data into dev.
            $this->line('4/4 Restoring production data into development…');
            $this->runProcess([
                $bin['pg_restore'], ...$connArgs($target),
                '--dbname='.$target['database'],
                '--no-owner', '--no-acl', '--exit-on-error', '--single-transaction',
                $prodDump,
            ], $target['password'] ?? '');

            DB::purge($targetName);
        } catch (\Throwable $e) {
            $this->error('Sync failed: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            if (is_file($prodDump)) {
                @unlink($prodDump);
            }
        }

        $this->info(sprintf('Done. "%s" now mirrors "%s".', $target['database'], $source['database']));
        if (is_file($devBackup)) {
            $this->line("Previous development data saved at {$devBackup}");
        }

        return self::SUCCESS;
    }

    /**
     * pg_dump refuses servers newer than itself, so prefer the client binaries
     * matching the server's major version (/usr/lib/postgresql/<major>/bin),
     * falling back to whatever is on PATH.
     *
     * @return array{pg_dump: string, pg_restore: string, psql: string}
     */
    private function clientBinaries(string $connection): array
    {
        $versionNum = (int) (DB::connection($connection)->selectOne("select current_setting('server_version_num') as v")->v ?? 0);
        $major = $versionNum >= 100000 ? intdiv($versionNum, 10000) : null;

        $tools = [];
        foreach (['pg_dump', 'pg_restore', 'psql'] as $tool) {
            $candidate = $major ? "/usr/lib/postgresql/{$major}/bin/{$tool}" : null;
            $tools[$tool] = ($candidate && is_executable($candidate)) ? $candidate : $tool;
        }

        return $tools;
    }

    private function quoteIdent(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }

    /**
     * Run a PostgreSQL client process with PGPASSWORD set, throwing on failure.
     *
     * @param array<int, string> $command
     */
    private function runProcess(array $command, string $password): void
    {
        $process = new Process($command, null, ['PGPASSWORD' => $password, 'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin']);
        $process->setTimeout(self::PROCESS_TIMEOUT);
        $process->run();

        if (! $process->isSuccessful()) {
            $message = trim($process->getErrorOutput()) ?: trim($process->getOutput());
            throw new \RuntimeException($message ?: 'process exited with code '.$process->getExitCode());
        }
    }
}
