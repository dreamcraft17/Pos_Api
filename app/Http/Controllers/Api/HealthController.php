<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\DB;

class HealthController
{
    public function health()
    {
        return ['ok' => true, 'time' => now()->toISOString()];
    }

    public function dbCheck()
    {
        try {
            DB::select('SELECT 1');
            return ['ok' => true, 'db' => 'up'];
        } catch (\Throwable $e) {
            return response()->json(['ok'=>false,'error'=>$e->getMessage()], 500);
        }
    }
}
