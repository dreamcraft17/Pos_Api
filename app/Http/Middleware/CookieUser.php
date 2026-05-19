<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;

class CookieUser
{
    public function handle($request, Closure $next)
    {
        $uid = $request->cookie('uid');
        if ($uid && ($u = User::find($uid))) {
            $request->attributes->set('auth_user', $u);
        }
        return $next($request);
    }
}
