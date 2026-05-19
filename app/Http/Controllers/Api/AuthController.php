<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Symfony\Component\HttpFoundation\Cookie;

class AuthController extends Controller
{
    /**
     * Register user baru (opsional kalau sudah ada)
     */
    public function register(Request $r)
    {
        $data = $r->validate([
            'username' => 'required|string|min:3|max:64|unique:users,username',
            'password' => 'required|string|min:6|max:100',
            'display_name' => 'nullable|string|max:100',
        ]);

        $u = User::create([
            'username'     => $data['username'],
            'password'     => Hash::make($data['password']),
            'display_name' => $data['display_name'] ?? null,
        ]);

        return $this->issueCookieAndRespond($u);
    }

    /**
     * Login username + password
     */
    public function login(Request $r)
    {
        $data = $r->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $u = User::where('username', $data['username'])->first();
        if (!$u || !Hash::check($data['password'], $u->password)) {
            throw ValidationException::withMessages([
                'username' => ['Username or password incorrect'],
            ]);
        }

        return $this->issueCookieAndRespond($u);
    }

    /**
     * Logout (hapus cookie)
     */
    public function logout()
    {
        // Hapus cookie uid (set expire ke lalu)
        return response()->json(['status' => 'ok', 'message' => 'logged-out'])
            ->withCookie(Cookie::create('uid', '', -60, '/', null, true, true, false, 'Lax'));
    }

    /**
     * Ambil user saat ini (dipakai Flutter: harus balikin key 'user')
     */
    public function me(Request $r)
    {
        /** @var User|null $user */
        $user = $r->attributes->get('auth_user');
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'unauthenticated'], 401);
        }
        return response()->json(['status' => 'ok', 'user' => $user]);
    }

    /**
     * Buat cookie sesi + balikan payload dengan key 'user'
     *
     * NOTE:
     * - Jika front-end beda origin (subdomain/domain berbeda),
     *   gunakan SameSite=None & Secure=true (HARUS HTTPS).
     * - Kalau 1 origin, Lax juga bisa, tapi produksi idealnya HTTPS+Secure.
     */
    // protected function issueCookieAndRespond(User $u)
    // {
    //     $isCrossSite = filter_var(config('app.cross_site_cookie', false), FILTER_VALIDATE_BOOLEAN);

    //     $sameSite = $isCrossSite ? 'None' : 'Lax';
    //     $secure   = $isCrossSite ? true   : config('app.cookie_secure', false);

    //     // Misal pakai session token sederhana (gantilah dgn tokenmu sendiri)
    //     // Di proyekmu mungkin sudah ada logic middleware yg baca 'uid' -> auth_user
    //     $token = base64_encode($u->id . '|' . sha1($u->id . '|' . config('app.key')));

    //     $cookie = Cookie::create(
    //         'uid',                 // name
    //         $token,                // value
    //         60 * 24 * 7,           // minutes (7 hari)
    //         '/',                   // path
    //         null,                  // domain (biarkan null kecuali perlu subdomain khusus)
    //         $secure,               // secure
    //         true,                  // httpOnly
    //         false,                 // raw
    //         $sameSite              // SameSite
    //     );

    //     return response()
    //         ->json(['status' => 'ok', 'user' => $u])
    //         ->withCookie($cookie);
    // }

    protected function issueCookieAndRespond(User $u)
{
    $isCrossSite = filter_var(config('app.cross_site_cookie', false), FILTER_VALIDATE_BOOLEAN);

    $sameSite = $isCrossSite ? 'None' : 'Lax';
    $secure   = $isCrossSite ? true   : config('app.cookie_secure', false);

    // ⚠️ samakan rumus dengan middleware: decode APP_KEY kalau base64:...
    $appKey = config('app.key');
    if (\Illuminate\Support\Str::startsWith($appKey, 'base64:')) {
        $appKey = base64_decode(substr($appKey, 7));
    }

    $token = base64_encode($u->id . '|' . sha1($u->id . '|' . $appKey));

    $cookie = \Symfony\Component\HttpFoundation\Cookie::create(
        'uid',
        $token,
        60 * 24 * 7, // menit (7 hari)
        '/',
        null,        // set ke ".domainmu.com" kalau lintas subdomain
        $secure,
        true,
        false,
        $sameSite
    );

    return response()->json(['status' => 'ok', 'user' => $u])->withCookie($cookie);
}

}
