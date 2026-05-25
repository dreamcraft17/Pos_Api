<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireAuth
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->attributes->get('auth_user')
            ?? $request->user()
            ?? auth()->user();

        if (! $user) {
            return response()->json(['ok' => false, 'message' => 'unauthenticated'], 401);
        }

        return $next($request);
    }
}
