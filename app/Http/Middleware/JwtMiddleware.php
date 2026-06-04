<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;

class JwtMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            JWTAuth::parseToken()->authenticate();
        } catch (TokenExpiredException) {
            return response()->json(['error' => 'Token expired'], 401);
        } catch (TokenInvalidException) {
            return response()->json(['error' => 'Token invalid'], 401);
        } catch (JWTException) {
            return response()->json(['error' => 'Token not provided'], 401);
        }

        return $next($request);
    }
}
