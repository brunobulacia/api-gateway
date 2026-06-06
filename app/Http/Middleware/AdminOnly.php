<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class AdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (!$user || $user->role !== 'ADMIN') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
