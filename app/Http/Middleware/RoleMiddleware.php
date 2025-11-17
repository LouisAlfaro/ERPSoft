<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $roles)
    {
        $user = $request->user('api');

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $allowed = array_map('trim', explode('|', $roles));

        if (!$user->inAnyRole($allowed)) {
            return response()->json(['message' => 'Forbidden (role required)'], 403);
        }

        return $next($request);
    }
}
