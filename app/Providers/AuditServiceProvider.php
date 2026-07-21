<?php

namespace App\Providers;

use App\Support\Auditing\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the global audit trail.
 *
 * Instead of adding a trait to every model, we subscribe to Eloquent's wildcard
 * model events, so any model — current or future — that fires created / updated
 * / deleted / restored is recorded by App\Support\Auditing\AuditLogger.
 *
 * Note: pivot-only changes made via a relationship's attach/detach/sync do not
 * fire these model events and are therefore not captured here.
 */
class AuditServiceProvider extends ServiceProvider
{
    /**
     * Model events that map to an AuditLogger method of the same name.
     */
    protected array $events = ['created', 'updated', 'deleted', 'restored'];

    public function register(): void
    {
        $this->app->singleton(AuditLogger::class);
    }

    public function boot(): void
    {
        $logger = $this->app->make(AuditLogger::class);

        foreach ($this->events as $event) {
            Event::listen("eloquent.{$event}: *", function (string $eventName, array $payload) use ($logger, $event) {
                $model = $payload[0] ?? null;

                if ($model instanceof Model) {
                    $logger->{$event}($model);
                }
            });
        }
    }
}
