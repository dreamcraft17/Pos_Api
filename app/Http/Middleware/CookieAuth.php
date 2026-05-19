<?php
// app/Http/Middleware/CookieAuth.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class CookieAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->cookies->get('uid');

       // App/Http/Middleware/CookieAuth.php
if ($token) {
    $decoded = base64_decode($token, true);
    if ($decoded && str_contains($decoded, '|')) {
        [$id, $sig] = explode('|', $decoded, 2);

        $appKey = config('app.key');
        if (Str::startsWith($appKey, 'base64:')) {
            $appKey = base64_decode(substr($appKey, 7));
        }

        $expected = sha1($id.'|'.$appKey);

        dd([
            'token' => $token,
            'decoded' => $decoded,
            'id' => $id,
            'sig' => $sig,
            'expected' => $expected,
            'match' => hash_equals($expected, $sig ?? ''),
        ]);
    }
}


        return $next($request);
    }
}
