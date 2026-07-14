<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Replace the current (dev) database with a fresh copy of the production
 * database.
 *
 * Prod and dev share one PostgreSQL server and role, differing only by
 * database name, so this dumps the source DB and reloads it into the current
 * connection's DB after wiping the latter's `public` schema.
 *
 * SAFETY: the command only ever writes into the CURRENT connection's database
 * and aborts when that database is the same as the source. Because the prod
 * app runs against the source database itself, running this there is a no-op
 * refusal — it can never overwrite prod.
 */
class SyncDbFromProd extends Command
{
    protected $signature = 'db:sync-from-prod
                            {--force : Skip the interactive confirmation}';

    protected $description = 'Copy the production database into the current (dev) database, overwriting it';

    public function handle(): int
    {
        $source = config('dbsync.source');
        $target = config('database.connections.' . config('database.default'));

        // --- Guards --------------------------------------------------------
        if (($target['driver'] ?? null) !== 'pgsql') {
            $this->error('This command only supports the pgsql driver.');

            return self::FAILURE;
        }

        if (empty($source['database']) || empty($target['database'])) {
            $this->error('Source or target database name is not configured.');

            return self::FAILURE;
        }

        // The single most important guard: never write onto the source DB.
        // On production the app's own DB is the source, so this blocks there.
        if ($source['database'] === $target['database']
            && $source['host'] === ($target['host'] ?? null)) {
            $this->error(sprintf(
                'Refusing to run: source and target are the same database (%s@%s). '
                . 'This looks like the production environment.',
                $target['database'],
                $target['host'] ?? '?'
            ));

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

        // --- Run -----------------------------------------------------------
        $dumpFile = storage_path('app/db-sync-prod-dump.sql');
        @mkdir(dirname($dumpFile), 0775, true);

        try {
            $this->line('1/3 Dumping production database…');
            $this->runProcess([
                'pg_dump',
                '--host=' . $source['host'],
                '--port=' . $source['port'],
                '--username=' . $source['username'],
                '--no-owner',
                '--no-privileges',
                '--file=' . $dumpFile,
                $source['database'],
            ], $source['password']);

            $this->line('2/3 Wiping the dev schema…');
            $this->runProcess([
                'psql',
                '--host=' . $target['host'],
                '--port=' . ($target['port'] ?? 5432),
                '--username=' . $target['username'],
                '--dbname=' . $target['database'],
                '--set=ON_ERROR_STOP=1',
                '--command=DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;',
            ], $target['password'] ?? '');

            $this->line('3/3 Restoring production data into dev…');
            $this->runProcess([
                'psql',
                '--host=' . $target['host'],
                '--port=' . ($target['port'] ?? 5432),
                '--username=' . $target['username'],
                '--dbname=' . $target['database'],
                '--set=ON_ERROR_STOP=1',
                '--file=' . $dumpFile,
            ], $target['password'] ?? '');
        } catch (\RuntimeException $e) {
            $this->error('Sync failed: ' . $e->getMessage());

            return self::FAILURE;
        } finally {
            @unlink($dumpFile);
        }

        $this->info(sprintf('Done. "%s" now mirrors "%s".', $target['database'], $source['database']));

        return self::SUCCESS;
    }

    /**
     * Run a psql/pg_dump process with the given PGPASSWORD, throwing on failure.
     *
     * @param array<int, string> $command
     */
    private function runProcess(array $command, string $password): void
    {
        $process = new Process($command, null, ['PGPASSWORD' => $password]);
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            // Prefer stderr; fall back to stdout for the error message.
            $message = trim($process->getErrorOutput()) ?: trim($process->getOutput());
            throw new \RuntimeException($message ?: 'process exited with code ' . $process->getExitCode());
        }
    }
}
