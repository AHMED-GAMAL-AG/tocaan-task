<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // "No query results for model [App\Models\Order] 7" -> "Order not found."
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*') || ! $e->getPrevious() instanceof ModelNotFoundException) {
                return null;
            }

            return response()->json([
                'message' => class_basename($e->getPrevious()->getModel()).' not found.',
            ], 404);
        });

        // Raised when refreshing a missing, malformed, blacklisted or too-old token.
        $exceptions->render(fn (JWTException $e, Request $request) => response()->json([
            'message' => $e->getMessage() ?: 'Token could not be refreshed.',
            'error' => 'invalid_token',
        ], 401));
    })->create();
