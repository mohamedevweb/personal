<?php

use App\Exceptions\ContentGenerationException;
use App\Exceptions\InstagramIntegrationException;
use App\Http\Middleware\AuthenticateFromTokenCookie;
use App\Http\Middleware\UseRequestLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->throttleApi();
        $middleware->api(prepend: [UseRequestLocale::class, AuthenticateFromTokenCookie::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        $exceptions->render(function (InstagramIntegrationException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        });

        $exceptions->render(function (ContentGenerationException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], Response::HTTP_SERVICE_UNAVAILABLE);
            }
        });
    })->create();
