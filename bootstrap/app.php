<?php

use App\Support\Cabinet\Cabinet;
use App\Support\DevEnvironment;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\App\Http\Middleware\CabinetBoundary::class);

        $middleware->web(append: [
            \App\Http\Middleware\VerifyCsrfToken::class,
            \App\Http\Middleware\SwitchDatabaseConnection::class,
            \App\Http\Middleware\SetLocale::class,
        ]);

        // The developer DB switch needs the session but must run before route
        // model binding (and anything else) touches the database.
        $middleware->prependToPriorityList(
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\SwitchDatabaseConnection::class
        );

        $middleware->alias([
            'backpack.can' => \App\Http\Middleware\CheckBackpackPermission::class,
            'cabinet.auth' => \App\Http\Middleware\AuthenticateCabinet::class,
        ]);

        // Staff login keeps its current redirect. Cabinet guests go to the
        // cabinet home, and cabinet users who are already signed in leave /login.
        $middleware->redirectUsersTo(function (Request $request) {
            if (Cabinet::isHost($request)) {
                return route('cabinet.home');
            }

            return '/';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! Cabinet::isHost($request)) {
                return null;
            }

            $status = $e->getStatusCode();

            if (! in_array($status, [403, 404, 419, 429], true)) {
                return null;
            }

            return Cabinet::secure(response()->view('cabinet.errors.generic', [
                'code' => $status,
            ], $status));
        });
    })->create();

// Host-based development mode (dev.mirrorgallery.ge): decided right after the
// configuration is loaded, before any service provider registers. CLI runs
// (artisan, scheduler, queue) are never affected.
$app->afterBootstrapping(
    LoadConfiguration::class,
    fn (Application $app) => DevEnvironment::detect($app)
);

return $app;
