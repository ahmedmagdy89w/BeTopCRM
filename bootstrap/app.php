<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi(); // Enables Sanctum's session cookie authentication for SPA

        $middleware->appendToGroup('api', SetLocale::class);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        /**
         * Force all API (and Accept: application/json) responses to JSON.
         * This ensures consistent error payloads for every failure mode.
         */

        // 422 Unprocessable Entity — validation failures
        $exceptions->render(function (ValidationException $e, $request): ?JsonResponse {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null; // let default web handler take over
            }

            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        });

        // 404 Not Found — route not found or model not found
        $exceptions->render(function (NotFoundHttpException $e, $request): ?JsonResponse {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            // Unwrap ModelNotFoundException for a friendlier message
            $previous = $e->getPrevious();
            $message = $previous instanceof ModelNotFoundException
                ? class_basename($previous->getModel()).' not found.'
                : 'The requested resource was not found.';

            return response()->json(['message' => $message], JsonResponse::HTTP_NOT_FOUND);
        });

        // 401 Unauthenticated
        $exceptions->render(function (AuthenticationException $e, $request): ?JsonResponse {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json(
                ['message' => 'Unauthenticated.'],
                JsonResponse::HTTP_UNAUTHORIZED,
            );
        });

        // 403 Forbidden — policy / Gate / FormRequest denials
        $exceptions->render(function (AuthorizationException $e, $request): ?JsonResponse {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null; // let default web handler take over
            }

            return response()->json(
                ['message' => $e->getMessage() ?: 'This action is unauthorized.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        });

        // Generic HTTP exceptions (403, 405, 429, …)
        $exceptions->render(function (HttpException $e, $request): ?JsonResponse {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json(
                ['message' => $e->getMessage() ?: 'HTTP error.'],
                $e->getStatusCode(),
            );
        });

    })->create();
