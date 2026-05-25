<?php

namespace App\Http\Controllers\Api;

use App\Models\StockMove;
use Illuminate\Http\Request;

class StockController extends BaseApiController
{
    public function index(Request $r)
    {
        $q = StockMove::query();

        if ($sku = $r->query('sku')) {
            $q->where('sku', $sku);
        }

        if ($date = $r->query('date')) {
            $q->whereDate('created_at', $date);
        }

        if ($from = $r->query('from')) {
            $q->whereDate('created_at', '>=', $from);
        }

        if ($to = $r->query('to')) {
            $q->whereDate('created_at', '<=', $to);
        }

        $limit = max(1, min(
            (int) $r->query('limit', config('pos.limits.stock_moves_default', 500)),
            config('pos.limits.stock_moves_max', 5000)
        ));

        return $q->orderBy('created_at')->limit($limit)->get();
    }
}
