<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\JwtMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // $middleware->alias([
        //     'jwt.verify' => JwtMiddleware::class
        // ]);
        // $middleware->append(JwtMiddleware::class,'auth:api'); // Ensure this is not applied globally
        // $middleware->append(\Tymon\JWTAuth\Http\Middleware\Check::class, 'auth:api'); // Ensure this is not applied globally
    })
    ->withExceptions(function (Exceptions $exceptions) {

        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $exception, $request) {
            return response()->json([
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'statusCode' => 401,
                'status' => 'UNAUTHORIZED',
                'message' => 'Unauthenticated. Your token may have expired.',
            ], 401);
        });
        $exceptions->renderable(function (\Illuminate\Auth\Access\AuthorizationException $exception, $request) {
            return response()->json([
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'statusCode' => 403,
                'status' => 'FORBIDDEN',
                'message' => 'Forbidden. You do not have permission to access this resource.',
            ], 403);
        });

        $exceptions->renderable(function (\Illuminate\Validation\ValidationException $exception, $request) {
            return response()->json([
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'statusCode' => 422,
                'status' => 'UNPROCESSABLE_ENTITY',
                'message' => 'Validation failed.',
                'errors' => $exception->errors(),
            ], 422);
        });

        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\HttpException $exception, $request) {
            return response()->json([
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'statusCode' => $exception->getStatusCode(),
                'status' => 'ERROR',
                'message' => $exception->getMessage() ?: 'An error occurred.',
            ], $exception->getStatusCode());
        });

        $exceptions->renderable(function (\Throwable $exception, $request) {
            return response()->json([
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'statusCode' => 500,
                'status' => 'ERROR',
                'message' => $exception->getMessage() ?: 'An internal server error occurred.',
            ], 500);
        });
    })->create();
