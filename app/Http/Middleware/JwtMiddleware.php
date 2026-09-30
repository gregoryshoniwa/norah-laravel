<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class JwtMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next)
    {
        try {
            // Attempt to parse and authenticate the token
            $user = JWTAuth::parseToken()->authenticate();

            // authenticate() returns false (it does not throw) when the token
            // is well-formed but its subject no longer exists, e.g. a deleted
            // account. Treat that as unauthenticated rather than letting the
            // request through with no user.
            if (!$user) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Token subject not found.',
                ], 401);
            }
        } catch (TokenExpiredException $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Token has expired.',
            ], 401);
        } catch (TokenInvalidException $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Token is invalid.',
            ], 401);
        } catch (JWTException $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Token not provided or could not be parsed.',
            ], 401);
        }

        return $next($request);
    }
}
