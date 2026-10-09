<?php

namespace App\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;

/**
 * One codebase, two faces.
 *
 * - Host-based dev mode: requests arriving on a host listed in
 *   `app.dev_hosts` (DEV_HOSTS in .env, e.g. dev.mirrorgallery.ge) run as a
 *   local/debug application against the development database — for every
 *   user, no role required. {@see self::detect()} is hooked in
 *   bootstrap/app.php right after the configuration is loaded, so it runs
 *   before any service provider registers.
 *
 * - Session switch: a user with the `developer` role can point their own
 *   session at the dev database from the production host
 *   ({@see \App\Http\Middleware\SwitchDatabaseConnection}). Both paths end
 *   in {@see self::useDev()}.
 *
 * Sessions, cache and the queue are pinned to the production connection in
 * .env (SESSION_CONNECTION / DB_CACHE_CONNECTION / DB_QUEUE_CONNECTION), so
 * flipping `database.default` never logs anyone out.
 *
 * CLI (artisan, scheduler, queue worker) is never affected by host detection.
 */
class DevEnvironment
{
    /** Session key holding the developer's choice ('dev' when switched). */
    public const SESSION_KEY = 'db_connection';

    /** True once the current request has been put into host-based dev mode. */
    private static bool $hostActive = false;

    /** True once the current request's default connection was switched to dev. */
    private static bool $devDbActive = false;

    /**
     * Put the application into development mode when the request arrives on a
     * dev host. Runs after LoadConfiguration, before providers register.
     */
    public static function detect(Application $app): void
    {
        self::$hostActive = false;
        self::$devDbActive = false;

        if ($app->runningInConsole() || ! $app->bound('request')) {
            return;
        }

        $request = $app->make('request');
        $host = strtolower((string) $request->getHost());

        if ($host === '' || ! self::isDevHost($host)) {
            return;
        }

        $config = $app->make('config');

        // Environment + debugging
        $app->instance('env', 'local');
        $config->set('app.env', 'local');
        $config->set('app.debug', true);
        $config->set('debugbar.enabled', true);

        foreach ((array) $config->get('logging.channels', []) as $name => $channel) {
            if (is_array($channel) && array_key_exists('level', $channel)) {
                $config->set("logging.channels.{$name}.level", 'debug');
            }
        }

        // URLs: the app and every disk URL derived from it must point at the
        // dev host, otherwise Basset fonts/assets are fetched from production.
        $oldUrl = rtrim((string) $config->get('app.url'), '/');
        $newUrl = $request->getSchemeAndHttpHost();
        $config->set('app.url', $newUrl);

        foreach ((array) $config->get('filesystems.disks', []) as $disk => $options) {
            $url = $options['url'] ?? null;
            if ($oldUrl !== '' && is_string($url) && str_starts_with($url, $oldUrl)) {
                $config->set("filesystems.disks.{$disk}.url", $newUrl.substr($url, strlen($oldUrl)));
            }
        }

        self::$hostActive = true;
        self::useDev();
    }

    /**
     * Point the default database connection at the development database and
     * make sure nothing produced there can reach a real customer.
     */
    public static function useDev(): void
    {
        $dev = self::devConnection();

        if (! config("database.connections.{$dev}")) {
            return;
        }

        config(['database.default' => $dev]);

        if (app()->resolved('db')) {
            DB::setDefaultConnection($dev);
        }

        // Outbound side effects off: this project has no SMS gateway; mail is
        // the only channel that could reach a real person.
        config(['mail.default' => 'log']);

        self::$devDbActive = true;
    }

    /** Whether the current request is served on a dev host. */
    public static function isActive(): bool
    {
        return self::$hostActive;
    }

    /** Whether the default connection has been switched to the dev DB (by host or session). */
    public static function isDevDbActive(): bool
    {
        return self::$devDbActive;
    }

    public static function isDevHost(string $host): bool
    {
        $host = strtolower(trim($host));

        foreach ((array) config('app.dev_hosts', []) as $candidate) {
            if ($host === strtolower(trim((string) $candidate))) {
                return true;
            }
        }

        return false;
    }

    /** Connection name of the production database (never changes at runtime). */
    public static function prodConnection(): string
    {
        return (string) config('database.prod_connection', 'pgsql');
    }

    /** Connection name of the development database. */
    public static function devConnection(): string
    {
        return (string) config('database.dev_connection', 'pgsql_dev');
    }

    public static function prodDatabaseName(): ?string
    {
        return config('database.connections.'.self::prodConnection().'.database');
    }

    public static function devDatabaseName(): ?string
    {
        return config('database.connections.'.self::devConnection().'.database');
    }

    /**
     * True when the current default connection's database IS the dev database.
     */
    public static function isDevDatabase(): bool
    {
        $default = (string) config('database.default');
        $current = config("database.connections.{$default}.database");
        $dev = self::devDatabaseName();

        return $current !== null && $dev !== null && $current !== '' && $current === $dev
            && $current !== self::prodDatabaseName();
    }
}
