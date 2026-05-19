 <?php

// namespace App\Http\Controllers\Api;

// use Illuminate\Http\Request;
// use App\Models\StockMove;

// class StockController extends BaseApiController
// {
//     // public function index(Request $r)
//     // {
//     //     $u = $this->currentUser($r);
//     //     $q = StockMove::query();
//     //     if ($u) $q->where('created_by',$u->id);
//     //     if ($r->query('sku')) $q->where('sku',$r->query('sku'));
//     //     return $q->orderByDesc('id')->limit(500)->get();
//     // }

//      public function index(Request $r)
//     {
//         $q = StockMove::query();

//         // filter per SKU kalau dikirim
//         if ($sku = $r->query('sku')) {
//             $q->where('sku', $sku);
//         }

//         // optional: filter per tanggal / range
//         if ($date = $r->query('date')) {
//             $q->whereDate('created_at', $date);
//         }

//         if ($from = $r->query('from')) {
//             $q->whereDate('created_at', '>=', $from);
//         }

//         if ($to = $r->query('to')) {
//             $q->whereDate('created_at', '<=', $to);
//         }

//         // untuk history enaknya urut kronologis
//         return $q->orderBy('created_at')->get();
//     }

// } 




// namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\StockMove;

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

        $limit = max(1, min((int) $r->query('limit', 500), 5000));

        return $q->orderBy('created_at')->limit($limit)->get();
    }
}
