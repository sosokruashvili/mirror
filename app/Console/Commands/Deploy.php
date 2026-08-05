<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Runs everything that must happen after new code lands on a server.
 *
 * This exists because the steps are order-sensitive and easy to half-do by hand.
 * The failure it prevents: on 2026-08-05 the Supplier Prices page shipped to prod
 * with its migration run, but its permissions never appeared and the page 404'd —
 * prod was still serving a config and route cache built four days earlier, so
 * config('access.pages') had no 'supplier-price' key and the seeder (had it been
 * run at all) would have created nothing.
 *
 * Hence the ordering below, which is the whole point of the command:
 *   1. drop the stale caches FIRST, so step 3 reads the config file that just
 *      arrived rather than the one baked in at the last deploy;
 *   2. migrate;
 *   3. sync config/access.php -> page permissions;
 *   4. only then rebuild the caches.
 *
 * Seeding before clearing, or caching before seeding, silently reproduces the
 * original bug. Safe to re-run: every step is idempotent.
 */
class Deploy extends Command
{
    protected $signature = 'app:deploy
                            {--no-migrate : Skip database migrations}
                            {--no-cache : Leave caches cleared instead of rebuilding them}
                            {--cache : Rebuild caches even outside production}';

    protected $description = 'Post-deploy: clear caches, migrate, sync access permissions, rebuild caches';

    public function handle(): int
    {
        $this->newLine();
        $this->line('Deploying <info>' . config('app.name') . '</info> (' . app()->environment() . ')');
        $this->newLine();

        // 1. Stale config/route caches are what break everything downstream.
        $this->step('Clearing caches');
        $this->callSilent('config:clear');
        $this->callSilent('route:clear');
        $this->callSilent('view:clear');
        $this->done();

        // 2. Schema first, so the seeder can rely on the tables existing.
        if ($this->option('no-migrate')) {
            $this->step('Migrations');
            $this->line(' <comment>skipped</comment>');
        } else {
            $this->step('Running migrations');
            $this->callSilent('migrate', ['--force' => true]);
            $this->done();
        }

        // 3. config/access.php is the source of truth for page permissions;
        //    this is the step whose absence started all of this.
        $this->step('Syncing access permissions');
        $this->callSilent('db:seed', [
            '--class' => 'AccessPermissionSeeder',
            '--force' => true,
        ]);
        $this->done($this->permissionSummary());

        // 4. Rebuild only once the fresh config has been consumed above.
        //    Skipped outside production on purpose: a cached config on dev means
        //    .env edits silently do nothing, which costs more than it saves.
        $this->step('Rebuilding caches');

        if ($this->option('no-cache')) {
            $this->line(' <comment>skipped</comment>');
        } elseif (! app()->isProduction() && ! $this->option('cache')) {
            $this->line(' <comment>skipped (not production; --cache to force)</comment>');
        } else {
            $this->callSilent('config:cache');
            $this->callSilent('route:cache');
            $this->done();
        }

        $this->newLine();
        $this->info('Deploy complete.');
        $this->line('New pages still need granting to non-super roles under Admin > Roles.');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Count what the permission table now holds, as a sanity line in the output.
     */
    protected function permissionSummary(): string
    {
        $pages = count(config('access.pages', []));
        $perms = \App\Models\Permission::where('type', 'page')->count();

        return "{$perms} permissions across {$pages} pages";
    }

    protected function step(string $label): void
    {
        $this->output->write('  ' . str_pad($label, 32, '.'));
    }

    protected function done(?string $detail = null): void
    {
        $this->line(' <info>ok</info>' . ($detail ? " <comment>({$detail})</comment>" : ''));
    }
}
