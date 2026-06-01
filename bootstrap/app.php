<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Exceptions\ApiException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

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
        $exceptions->render(function (\Throwable $e, Request $request) {
            $isApi = $request->is('api/*') || $request->expectsJson();
            if (! $isApi) {
                return null;
            }

            $requestId = $request->headers->get('X-Request-Id') ?: (string) Str::uuid();

            $base = [
                'data' => null,
                'message' => 'Server error',
                'errors' => null,
                'error_code' => 'SERVER_ERROR',
                'request_id' => $requestId,
            ];

            // Business / domain errors we throw intentionally
            if ($e instanceof ApiException) {
                return response()->json([
                    ...$base,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors,
                    'error_code' => $e->errorCode,
                ], $e->status)->withHeaders(['X-Request-Id' => $requestId]);
            }

            // 422 Validation
            if ($e instanceof ValidationException) {
                return response()->json([
                    ...$base,
                    'message' => $e->getMessage() ?: 'Validation error',
                    'errors' => $e->errors(),
                    'error_code' => 'VALIDATION_ERROR',
                ], 422)->withHeaders(['X-Request-Id' => $requestId]);
            }

            // 401 Authentication (including JWT token issues)
            if ($e instanceof AuthenticationException) {
                return response()->json([
                    ...$base,
                    'message' => 'Unauthenticated',
                    'error_code' => 'UNAUTHENTICATED',
                ], 401)->withHeaders(['X-Request-Id' => $requestId]);
            }

            if ($e instanceof TokenExpiredException) {
                return response()->json([
                    ...$base,
                    'message' => 'Token expired',
                    'error_code' => 'TOKEN_EXPIRED',
                ], 401)->withHeaders(['X-Request-Id' => $requestId]);
            }

            if ($e instanceof TokenInvalidException) {
                return response()->json([
                    ...$base,
                    'message' => 'Token invalid',
                    'error_code' => 'TOKEN_INVALID',
                ], 401)->withHeaders(['X-Request-Id' => $requestId]);
            }

            if ($e instanceof JWTException) {
                return response()->json([
                    ...$base,
                    'message' => 'Token error',
                    'error_code' => 'TOKEN_ERROR',
                ], 401)->withHeaders(['X-Request-Id' => $requestId]);
            }

            // 403 Authorization
            if ($e instanceof AuthorizationException) {
                return response()->json([
                    ...$base,
                    'message' => 'Forbidden',
                    'error_code' => 'FORBIDDEN',
                ], 403)->withHeaders(['X-Request-Id' => $requestId]);
            }

            // 404 Not Found (models & routes)
            if ($e instanceof ModelNotFoundException) {
                return response()->json([
                    ...$base,
                    'message' => 'Resource not found',
                    'error_code' => 'NOT_FOUND',
                ], 404)->withHeaders(['X-Request-Id' => $requestId]);
            }

            if ($e instanceof NotFoundHttpException) {
                return response()->json([
                    ...$base,
                    'message' => 'Route not found',
                    'error_code' => 'ROUTE_NOT_FOUND',
                ], 404)->withHeaders(['X-Request-Id' => $requestId]);
            }

            // 405
            if ($e instanceof MethodNotAllowedHttpException) {
                return response()->json([
                    ...$base,
                    'message' => 'Method not allowed',
                    'error_code' => 'METHOD_NOT_ALLOWED',
                    'errors' => [
                        'allowed' => Arr::get($e->getHeaders(), 'Allow'),
                    ],
                ], 405)->withHeaders(['X-Request-Id' => $requestId]);
            }

            // Other HTTP exceptions (e.g. 429, 400, etc.)
            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
                $message = $e->getMessage() ?: 'HTTP error';

                return response()->json([
                    ...$base,
                    'message' => $message,
                    'error_code' => 'HTTP_ERROR',
                ], $status)->withHeaders(['X-Request-Id' => $requestId]);
            }

            // Fallback: 500
            logger()->error('API unhandled exception', [
                'request_id' => $requestId,
                'method' => $request->method(),
                'path' => $request->path(),
                'ip' => $request->ip(),
                'user_admin_id' => optional(auth('admin_api')->user())->id,
                'user_store_id' => optional(auth('store_api')->user())->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return response()->json($base, 500)->withHeaders(['X-Request-Id' => $requestId]);
        });
    })->create();
