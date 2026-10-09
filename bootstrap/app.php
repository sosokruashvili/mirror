<?php

use App\Support\Cabinet\Cabinet;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\App\Http\Middleware\CabinetBoundary::class);

        $middleware->web(append: [
            \App\Http\Middleware\VerifyCsrfToken::class,
            \App\Http\Middleware\SetLocale::class,
        ]);

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
