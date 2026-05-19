<?php

// // namespace App\Http\Controllers\Api;

// // use Illuminate\Http\Request;
// // use Illuminate\Support\Facades\DB;
// // use App\Models\Product;
// // use App\Models\StockMove;

// // class ProductController extends BaseApiController
// // {
// //     public function index(Request $r)
// //     {
// //         $updatedSince = $r->query('updatedSince');
// //         $u = $this->currentUser($r);

// //         $q = Product::where('is_deleted', false);

// //         if ($updatedSince) {
// //             $q->where('updated_at', '>=', $updatedSince);
// //         }

// //         // Jika ada user terautentikasi, batasi pada produk yang dia buat.
// //         // if ($u) {
// //         //     $q->where('created_by', $u->id);
// //         // }

// //         return $q->orderBy('id')->get([
// //             'id', 'sku', 'name', 'price_cents', 'stock','category', 'updated_at'
// //         ]);
// //     }

// //     public function store(Request $r)
// //     {
// //         $data = $r->validate([
// //             'sku'         => 'required',
// //             'name'        => 'required',
// //             'price_cents' => 'required|integer|min:0',
// //             'stock'       => 'nullable|integer|min:0',
// //             'category'    => 'nullable|string|max:100',
// //         ]);

// //         // Ambil user JIKA ada; kalau tidak, biarkan null.
// //         $u = $this->currentUser($r);
// //         $uid = $u?->id;

// //         $prod = Product::firstOrNew(['sku' => $data['sku']]);
// //         $isNew = !$prod->exists;

// //         $prod->fill([
// //             'name'        => $data['name'],
// //             'price_cents' => $data['price_cents'],
// //             'stock'       => $data['stock'] ?? 0,
// //             'category'    => $data['category'] ?? null,
// //             'is_deleted'  => false,
// //         ]);

// //         // Saat CREATE baru, isi created_by dengan ID user jika ada; kalau tidak ada, biarkan null.
// //         if ($isNew && $uid) {
// //             $prod->created_by = $uid;
// //         }

// //         $prod->save();

// //         return ['ok' => true];
// //     }

// //     public function update(Request $r, $sku)
// //     {
// //         $data = $r->validate([
// //             'name'        => 'sometimes',
// //             'price_cents' => 'sometimes|integer|min:0',
// //             'stock'       => 'sometimes|integer|min:0',
// //             'category'    => 'sometimes|string|max:100',
// //         ]);

// //         if (!$data) {
// //             return response()->json(['message' => 'no fields to update'], 400);
// //         }

// //         Product::where('sku', $sku)
// //             ->where('is_deleted', false)
// //             ->update($data + ['updated_at' => now()]);

// //         return ['ok' => true];
// //     }

// //     public function adjustStock(Request $r, $sku)
// //     {
// //         $data = $r->validate([
// //             'delta'  => 'required|integer',
// //             'reason' => 'nullable|string',
// //         ]);

// //         // Ambil user JIKA ada; kalau tidak, biarkan null (stock move tetap tercatat).
// //         $u = $this->currentUser($r);
// //         $uid = $u?->id;

// //         DB::transaction(function () use ($sku, $data, $uid) {
// //             $p = Product::where('sku', $sku)
// //                 ->where('is_deleted', false)
// //                 ->firstOrFail();

// //             StockMove::create([
// //                 'sku'        => $sku,
// //                 'delta'      => $data['delta'],
// //                 'reason'     => $data['reason'] ?? null,
// //                 'created_by' => $uid, // isi user ID jika ada; jika tidak, null
// //             ]);

// //             $new = max(0, $p->stock + $data['delta']);
// //             $p->update(['stock' => $new, 'updated_at' => now()]);
// //         });

// //         return ['ok' => true];
// //     }

// //     public function softDelete(Request $r, $sku)
// //     {
// //         $affected = Product::where('sku', $sku)
// //             ->where('is_deleted', false)
// //             ->update(['is_deleted' => true, 'updated_at' => now()]);

// //         if ($affected === 0) {
// //             return response()->json(
// //                 ['ok' => false, 'message' => 'Product not found or already deleted'],
// //                 404
// //             );
// //         }

// //         return ['ok' => true, 'deleted' => $affected, 'soft' => true];
// //     }
// // }

// // <?php

// namespace App\Http\Controllers\Api;

// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\DB;
// use App\Models\Product;
// use App\Models\StockMove;

// class ProductController extends BaseApiController
// {
//     public function index(Request $r)
//     {
//         $updatedSince = $r->query('updatedSince');
//         $u = $this->currentUser($r);

//         $q = Product::where('is_deleted', false);

//         if ($updatedSince) {
//             $q->where('updated_at', '>=', $updatedSince);
//         }

//         // Jika perlu filter per user, bisa aktifkan ini:
//         // if ($u) {
//         //     $q->where('created_by', $u->id);
//         // }

//         return $q->orderBy('id')->get([
//             'id',
//             'sku',
//             'name',
//             'price_cents',
//             'stock',
//             'category',
//             'unit',       // << baru
//             'min_qty',    // << baru
//             'mandarin',
//             'brand',
//             'station',
//             'updated_at',
//         ]);
//     }

//     public function store(Request $r)
//     {
//         $data = $r->validate([
//             'sku'         => 'required',
//             'name'        => 'required',
//             'price_cents' => 'required|integer|min:0',
//             'stock'       => 'nullable|integer|min:0',
//             'category'    => 'nullable|string|max:100',
//             'unit'        => 'nullable|string|max:50',   // << baru
//             'min_qty'     => 'nullable|integer|min:0',   // << baru
//             'station'     => 'nullable|in:bar,kitchen',
//             'mandarin'    => 'nullable|string|max:100',
//             'brand'    => 'nullable|string|max:100',
//         ]);

//         // Ambil user JIKA ada; kalau tidak, biarkan null.
//         $u = $this->currentUser($r);
//         $uid = $u?->id;

//         $prod = Product::firstOrNew(['sku' => $data['sku']]);
//         $isNew = !$prod->exists;

//         $prod->fill([
//             'name'        => $data['name'],
//             'price_cents' => $data['price_cents'],
//             'stock'       => $data['stock'] ?? 0,
//             'category'    => $data['category'] ?? null,
//             'unit'        => $data['unit'] ?? null,      // << baru
//             'min_qty'     => $data['min_qty'] ?? null,      // << baru
//             'is_deleted'  => false,
//             'station'     => $data['station'] ?? null,
//             'mandarin'    => $data['mandarin'] ?? null,
//             'brand'       => $data['brand'] ?? null,
//         ]);

//         // Saat CREATE baru, isi created_by dengan ID user jika ada; kalau tidak ada, biarkan null.
//         if ($isNew && $uid) {
//             $prod->created_by = $uid;
//         }

//         $prod->save();

//         return ['ok' => true];
//     }

//     public function update(Request $r, $sku)
//     {
//         $data = $r->validate([
//             'name'        => 'sometimes',
//             'price_cents' => 'sometimes|integer|min:0',
//             'stock'       => 'sometimes|integer|min:0',
//             'category'    => 'sometimes|string|max:100',
//             'unit'        => 'sometimes|string|max:50',   // << baru
//             'min_qty'     => 'sometimes|integer|min:0',   // << baru
//             'station' => 'nullable|in:bar,kitchen',
//             'mandarin'    => 'nullable|string|max:100',
//             'brand'    => 'nullable|string|max:500',
//         ]);

//         if (!$data) {
//             return response()->json(['message' => 'no fields to update'], 400);
//         }

//         Product::where('sku', $sku)
//             ->where('is_deleted', false)
//             ->update($data + ['updated_at' => now()]);

//         return ['ok' => true];
//     }

//     // public function adjustStock(Request $r, $sku)
//     // {
//     //     $data = $r->validate([
//     //         'delta'  => 'required|integer',
//     //         'reason' => 'nullable|string',
//     //     ]);

//     //     // Ambil user JIKA ada; kalau tidak, biarkan null (stock move tetap tercatat).
//     //     $u = $this->currentUser($r);
//     //     $uid = $u?->id;

//     //     DB::transaction(function () use ($sku, $data, $uid) {
//     //         $p = Product::where('sku', $sku)
//     //             ->where('is_deleted', false)
//     //             ->firstOrFail();

//     //         StockMove::create([
//     //             'sku'        => $sku,
//     //             'delta'      => $data['delta'],
//     //             'reason'     => $data['reason'] ?? null,
//     //             'created_by' => $uid, // isi user ID jika ada; jika tidak, null
//     //         ]);

//     //         $new = max(0, $p->stock + $data['delta']);
//     //         $p->update(['stock' => $new, 'updated_at' => now()]);
//     //     });

//     //     return ['ok' => true];
//     // }

    

//     public function softDelete(Request $r, $sku)
//     {
//         $affected = Product::where('sku', $sku)
//             ->where('is_deleted', false)
//             ->update(['is_deleted' => true, 'updated_at' => now()]);

//         if ($affected === 0) {
//             return response()->json(
//                 ['ok' => false, 'message' => 'Product not found or already deleted'],
//                 404
//             );
//         }

//         return ['ok' => true, 'deleted' => $affected, 'soft' => true];
//     }
// }




namespace App\Http\Controllers\Api;

use App\Services\MasterDataCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\StockMove;

class ProductController extends BaseApiController
{
    public function index(Request $r)
    {
        $updatedSince = $r->query('updatedSince');
        $u = $this->currentUser($r);

        $q = Product::where('is_deleted', false);

        if ($updatedSince) {
            $q->where('updated_at', '>=', $updatedSince);

            return $q->orderBy('id')->get([
                'id', 'sku', 'name', 'price_cents', 'stock', 'category',
                'unit', 'min_qty', 'mandarin', 'brand', 'station', 'updated_at',
            ]);
        }

        return Cache::remember(
            MasterDataCache::productsKey(null),
            MasterDataCache::ttl(),
            fn () => $q->orderBy('id')->get([
                'id', 'sku', 'name', 'price_cents', 'stock', 'category',
                'unit', 'min_qty', 'mandarin', 'brand', 'station', 'updated_at',
            ])
        );
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'sku'         => 'required',
            'name'        => 'required',
            'price_cents' => 'required|integer|min:0',
            'stock'       => 'nullable|integer|min:0',
            'category'    => 'nullable|string|max:100',
            'unit'        => 'nullable|string|max:50',
            'min_qty'     => 'nullable|integer|min:0',
            'station'     => 'nullable|in:bar,kitchen',
            'mandarin'    => 'nullable|string|max:100',
            'brand'       => 'nullable|string|max:100',
        ]);

        $u = $this->currentUser($r);
        $uid = $u?->id;

        $prod = Product::firstOrNew(['sku' => $data['sku']]);
        $isNew = !$prod->exists;

        $prod->fill([
            'name'        => $data['name'],
            'price_cents' => $data['price_cents'],
            'stock'       => $data['stock'] ?? 0,
            'category'    => $data['category'] ?? null,
            'unit'        => $data['unit'] ?? null,
            'min_qty'     => $data['min_qty'] ?? null,
            'is_deleted'  => false,
            'station'     => $data['station'] ?? null,
            'mandarin'    => $data['mandarin'] ?? null,
            'brand'       => $data['brand'] ?? null,
        ]);

        if ($isNew && $uid) {
            $prod->created_by = $uid;
        }

        $prod->save();

        MasterDataCache::forgetProducts();

        return ['ok' => true];
    }

    public function update(Request $r, $sku)
    {
        $data = $r->validate([
            'name'        => 'sometimes',
            'price_cents' => 'sometimes|integer|min:0',
            'stock'       => 'sometimes|integer|min:0',
            'category'    => 'sometimes|string|max:100',
            'unit'        => 'sometimes|string|max:50',
            'min_qty'     => 'sometimes|integer|min:0',
            'station'     => 'nullable|in:bar,kitchen',
            'mandarin'    => 'nullable|string|max:100',
            'brand'       => 'nullable|string|max:500',
        ]);

        if (!$data) {
            return response()->json(['message' => 'no fields to update'], 400);
        }

        Product::where('sku', $sku)
            ->where('is_deleted', false)
            ->update($data + ['updated_at' => now()]);

        MasterDataCache::forgetProducts();

        return ['ok' => true];
    }

    /**
     * 🔥 NEW: adjust stock, simpan ke stock_moves + stock_after
     * Route: POST /api/products/{sku}/stock
     */
    public function adjustStock(Request $r, $sku)
    {
        $data = $r->validate([
            'delta'  => 'required|integer',
            'reason' => 'nullable|string',
        ]);

        $u = $this->currentUser($r);
        $uid = $u?->id;

        DB::transaction(function () use ($sku, $data, $uid) {
            $p = Product::where('sku', $sku)
                ->where('is_deleted', false)
                ->lockForUpdate()
                ->firstOrFail();

            $delta     = (int)$data['delta'];
            $newStock  = max(0, $p->stock + $delta);

            StockMove::create([
                'sku'         => $sku,
                'delta'       => $delta,
                'stock_after' => $newStock,
                'reason'      => $data['reason'] ?? null,
                'created_by'  => $uid,
                'created_at'  => now(),
            ]);

            $p->update([
                'stock'      => $newStock,
                'updated_at' => now(),
            ]);
        });

        MasterDataCache::forgetProducts();

        return ['ok' => true];
    }

    public function softDelete(Request $r, $sku)
    {
        $affected = Product::where('sku', $sku)
            ->where('is_deleted', false)
            ->update(['is_deleted' => true, 'updated_at' => now()]);

        if ($affected === 0) {
            return response()->json(
                ['ok' => false, 'message' => 'Product not found or already deleted'],
                404
            );
        }

        MasterDataCache::forgetProducts();

        return ['ok' => true, 'deleted' => $affected, 'soft' => true];
    }
}
