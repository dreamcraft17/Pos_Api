<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CookieAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->cookies->get('uid');

        if ($token) {
            $decoded = base64_decode($token, true);
            if ($decoded && str_contains($decoded, '|')) {
                [$id, $sig] = explode('|', $decoded, 2);

                $appKey = config('app.key');
                if (Str::startsWith($appKey, 'base64:')) {
                    $appKey = base64_decode(substr($appKey, 7));
                }

                $expected = sha1($id.'|'.$appKey);

                if (hash_equals($expected, $sig ?? '') && ctype_digit($id)) {
                    $user = User::find((int) $id);
                    if ($user) {
                        $request->attributes->set('auth_user', $user);
                        auth()->setUser($user);
                    }
                }
            }
        }

        return $next($request);
    }
}
