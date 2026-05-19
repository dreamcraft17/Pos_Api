<?php

// app/Http/Controllers/Api/BaseApiController.php
namespace App\Http\Controllers\Api;

use App\Http\Concerns\NormalizesRupiahInput;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class BaseApiController extends Controller
{
    use NormalizesRupiahInput;
    protected function currentUser(Request $request)
    {
        // urutan fallback: attribute -> request()->user() -> auth()->user()
        return $request->attributes->get('auth_user')
            ?? $request->user()
            ?? auth()->user();
    }
}
