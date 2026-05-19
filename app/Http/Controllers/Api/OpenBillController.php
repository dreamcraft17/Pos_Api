<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OpenBill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OpenBillController extends Controller
{
    // GET /api/open-bills
    public function index(Request $request)
    {
        $user = null;

        $limit = max(1, min(
            (int) $request->query('limit', config('pos.limits.open_bills_default', 100)),
            config('pos.limits.open_bills_max', 500)
        ));

        $rows = OpenBill::query()
            ->where('user_id', $user?->id)
            ->where('status', 'open')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json($rows);
    }

    // POST /api/open-bills
    // Bisa create / update (by client_id)
    // public function store(Request $request)
    // {
    //     $user = null;

    //     $data = $request->validate([
    //         'client_id' => 'nullable|string|max:100',
    //         'status'    => 'nullable|string|max:20',
    //         'items'     => 'required|array|min:1',
    //         'items.*.qty'         => 'required|integer|min:1',
    //         'items.*.price_cents' => 'required|integer|min:0',
    //         // kalau mau lebih strict, tambahin rule sku/name/etc di sini
    //         'discount_cents' => 'nullable|integer',
    //         'tax_cents'      => 'nullable|integer',
    //         'total_cents'    => 'nullable|integer',
    //     ]);

    //     $payload = $request->all();

    //     // Hitung subtotal dari items
    //     $items = $payload['items'] ?? [];
    //     $subtotal = collect($items)->sum(function ($it) {
    //         $price = (int)($it['price_cents'] ?? 0);
    //         $qty   = (int)($it['qty'] ?? 0);
    //         return $price * $qty;
    //     });

    //     $discount = (int)($payload['discount_cents'] ?? 0);
    //     $tax      = (int)($payload['tax_cents'] ?? 0);
    //     $total    = (int)($payload['total_cents'] ?? ($subtotal - $discount + $tax));

    //     $clientId = $payload['client_id'] ?? null;
    //     $status   = $payload['status'] ?? 'open';

    //     return DB::transaction(function () use ($user, $clientId, $status, $subtotal, $discount, $tax, $total, $payload) {
    //         // updateOrCreate by (user_id, client_id) kalau client_id ada
    //         if ($clientId) {
    //             $bill = OpenBill::updateOrCreate(
    //                 [
    //                     'user_id'   => $user?->id,
    //                     'client_id' => $clientId,
    //                 ],
    //                 [
    //                     'status'         => $status,
    //                     'subtotal_cents' => $subtotal,
    //                     'discount_cents' => $discount,
    //                     'tax_cents'      => $tax,
    //                     'total_cents'    => $total,
    //                     'payload'        => $payload,
    //                 ]
    //             );
    //         } else {
    //             $bill = OpenBill::create([
    //                 'user_id'        => $user?->id,
    //                 'client_id'      => null,
    //                 'status'         => $status,
    //                 'subtotal_cents' => $subtotal,
    //                 'discount_cents' => $discount,
    //                 'tax_cents'      => $tax,
    //                 'total_cents'    => $total,
    //                 'payload'        => $payload,
    //             ]);
    //         }

    //         return response()->json($bill);
    //     });
    // }


//     public function store(Request $request)
// {
//     $user = null;

//     $data = $request->validate([
//         'client_id' => 'nullable|string|max:100',
//         'status'    => 'nullable|string|max:20',

//         // items batch awal (wajib – sama seperti sekarang)
//         'items'              => 'required|array|min:1',
//         'items.*.qty'        => 'required|integer|min:1',
//         'items.*.price_cents'=> 'required|integer|min:0',

//         // boleh dikirim dari client, tapi di-sanitize di server
//         'discount_cents' => 'nullable|integer',
//         'tax_cents'      => 'nullable|integer',
//         'total_cents'    => 'nullable|integer',

//         // edits tidak divalidasi terlalu ketat (optional)
//         // 'edits' => 'array',
//     ]);

//     // payload lengkap (items, edits, nama meja, dll)
//     $payload = $request->all();

//     $items = $payload['items'] ?? [];
//     $edits = $payload['edits'] ?? [];

//     // ====== 1) Hitung subtotal dari ITEMS (batch awal) ======
//     $rawSubtotal = collect($items)->sum(function ($it) {
//         $price = (int)($it['price_cents'] ?? 0);
//         $qty   = (int)($it['qty'] ?? 0);
//         return $price * $qty;
//     });

//     // ====== 2) Kurangi dengan VOID dari edits (kalau ada) ======
//     $voidTotal = 0;

//     if (is_array($edits)) {
//         foreach ($edits as $edit) {
//             $editItems = $edit['items'] ?? [];
//             if (!is_array($editItems)) {
//                 continue;
//             }

//             foreach ($editItems as $eItem) {
//                 // kita cuma peduli item yang memang VOID === true
//                 if (empty($eItem['void'])) {
//                     continue;
//                 }

//                 $qty   = (int)($eItem['qty'] ?? 0);
//                 $price = (int)($eItem['price_cents'] ?? 0);

//                 // kalau price_cents nggak dikirim di edits, fallback cari dari items utama pakai sku
//                 if ($price === 0 && !empty($eItem['sku'])) {
//                     $sku = $eItem['sku'];
//                     $found = collect($items)->firstWhere('sku', $sku);
//                     if ($found) {
//                         $price = (int)($found['price_cents'] ?? 0);
//                     }
//                 }

//                 if ($qty > 0 && $price > 0) {
//                     $voidTotal += $price * $qty;
//                 }
//             }
//         }
//     }

//     // subtotal efektif = subtotal awal - semua void
//     $subtotal = max(0, $rawSubtotal - $voidTotal);

//     // ====== 3) Discount / tax / total final ======
//     $discount = (int)($payload['discount_cents'] ?? 0);
//     $tax      = (int)($payload['tax_cents'] ?? 0);

//     // Kalau client kirim total_cents, boleh dipakai, tapi kalau nggak ada pakai rumus standar
//     $total    = (int)($payload['total_cents'] ?? ($subtotal - $discount + $tax));

//     $clientId = $payload['client_id'] ?? null;
//     $status   = $payload['status'] ?? 'open';

//     return DB::transaction(function () use ($user, $clientId, $status, $subtotal, $discount, $tax, $total, $payload) {
//         if ($clientId) {
//             // UPDATE (atau CREATE) by user_id + client_id
//             $bill = OpenBill::updateOrCreate(
//                 [
//                     'user_id'   => $user?->id,
//                     'client_id' => $clientId,
//                 ],
//                 [
//                     'status'         => $status,
//                     'subtotal_cents' => $subtotal,
//                     'discount_cents' => $discount,
//                     'tax_cents'      => $tax,
//                     'total_cents'    => $total,
//                     'payload'        => $payload,
//                 ]
//             );
//         } else {
//             // CREATE baru tanpa client_id
//             $bill = OpenBill::create([
//                 'user_id'        => $user?->id,
//                 'client_id'      => null,
//                 'status'         => $status,
//                 'subtotal_cents' => $subtotal,
//                 'discount_cents' => $discount,
//                 'tax_cents'      => $tax,
//                 'total_cents'    => $total,
//                 'payload'        => $payload,
//             ]);
//         }

//         return response()->json($bill);
//     });
// }


public function store(Request $request)
{
    $user = null;

    $data = $request->validate([
        'client_id' => 'nullable|string|max:100',
        'status'    => 'nullable|string|max:20',

        'items'               => 'required|array|min:1',
        'items.*.sku'         => 'required|string',
        'items.*.qty'         => 'required|integer|min:1',
        'items.*.price_cents' => 'required|integer|min:0',

        'discount_cents' => 'nullable|integer',
        'tax_cents'      => 'nullable|integer',
        'total_cents'    => 'nullable|integer',
    ]);

    // Payload full dari client (items + edits + dll)
    $payload = $request->all();

    $items = $payload['items'] ?? [];
    $edits = $payload['edits'] ?? [];

    // ================= 1) BUILD MAP DARI ITEMS AWAL =================
    // key: "sku|price" -> ['template' => itemMap, 'qty' => int]
    $agg = [];

    foreach ($items as $it) {
        if (!is_array($it)) {
            continue;
        }

        $sku = $it['sku'] ?? null;
        if (!$sku) {
            continue;
        }

        $price = (int)($it['price_cents']
            ?? $it['unit_price_cents']
            ?? 0);
        $qty = (int)($it['qty'] ?? 0);

        if ($price <= 0 || $qty <= 0) {
            continue;
        }

        $key = $sku.'|'.$price;

        if (!isset($agg[$key])) {
            // simpan template awal (tanpa qty)
            $template       = $it;
            $template['qty'] = 0;
            $template['price_cents'] = $price; // normalisasi
            $agg[$key] = [
                'template' => $template,
                'qty'      => 0,
            ];
        }

        $agg[$key]['qty'] += $qty;
    }

    // Helper: kalau di edits nggak ada price, ambil dari items awal
    $lookupPriceBySku = function (string $sku) use ($agg): ?int {
        foreach ($agg as $key => $row) {
            [$s, $p] = explode('|', $key);
            if ($s === $sku) {
                return (int)$p;
            }
        }
        return null;
    };

    // ================= 2) APPLY SEMUA EDITS (VOID & ADD) =================
    if (is_array($edits)) {
        foreach ($edits as $edit) {
            if (!is_array($edit)) {
                continue;
            }
            $editItems = $edit['items'] ?? [];
            if (!is_array($editItems)) {
                continue;
            }

            foreach ($editItems as $eItem) {
                if (!is_array($eItem)) {
                    continue;
                }

                $sku = $eItem['sku'] ?? null;
                if (!$sku) {
                    continue;
                }

                $qty = (int)($eItem['qty'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                // ambil price dari edits kalau ada, else fallback sku dari items awal
                $price = (int)($eItem['price_cents'] ?? 0);
                if ($price <= 0) {
                    $foundPrice = $lookupPriceBySku($sku);
                    if ($foundPrice === null) {
                        continue;
                    }
                    $price = $foundPrice;
                }

                $key = $sku.'|'.$price;

                $isVoid = !empty($eItem['void']); // true = pembatalan

                if (!isset($agg[$key])) {
                    // tambahan yang benar-benar baru (belum ada di items awal)
                    $template = $eItem;
                    $template['qty'] = 0;
                    $template['price_cents'] = $price;
                    $agg[$key] = [
                        'template' => $template,
                        'qty'      => 0,
                    ];
                }

                if ($isVoid) {
                    // VOID → kurangi qty
                    $agg[$key]['qty'] -= $qty;
                } else {
                    // TAMBAHAN → tambah qty
                    $agg[$key]['qty'] += $qty;
                }
            }
        }
    }

    // ================= 3) BANGUN ULANG ITEMS FINAL =================
    $finalItems = [];
    $subtotal   = 0;

    foreach ($agg as $key => $row) {
        $qty = (int)$row['qty'];
        if ($qty <= 0) {
            // qty habis karena full VOID → item dihapus dari daftar
            continue;
        }

        $item = $row['template'];
        $item['qty'] = $qty;

        $price = (int)$item['price_cents'];
        $subtotal += $price * $qty;

        $finalItems[] = $item;
    }

    // Ganti items di payload dengan versi FINAL (setelah void + tambahan)
    // $payload['items'] = $finalItems;
    $payload['final_items'] = $finalItems;


    // ================= 4) HITUNG DISCOUNT / TAX / TOTAL =================
    $discount = (int)($payload['discount_cents'] ?? 0);
    $tax      = (int)($payload['tax_cents'] ?? 0);

    // kalau client kirim total_cents, boleh dipakai,
    // tapi kalau tidak, pakai rumus standar
    $total    = (int)($payload['total_cents'] ?? ($subtotal - $discount + $tax));

    $clientId = $payload['client_id'] ?? null;
    $status   = $payload['status'] ?? 'open';

    return DB::transaction(function () use ($user, $clientId, $status, $subtotal, $discount, $tax, $total, $payload) {
        if ($clientId) {
            $bill = OpenBill::updateOrCreate(
                [
                    'user_id'   => $user?->id,
                    'client_id' => $clientId,
                ],
                [
                    'status'         => $status,
                    'subtotal_cents' => $subtotal,
                    'discount_cents' => $discount,
                    'tax_cents'      => $tax,
                    'total_cents'    => $total,
                    'payload'        => $payload,
                ]
            );
        } else {
            $bill = OpenBill::create([
                'user_id'        => $user?->id,
                'client_id'      => null,
                'status'         => $status,
                'subtotal_cents' => $subtotal,
                'discount_cents' => $discount,
                'tax_cents'      => $tax,
                'total_cents'    => $total,
                'payload'        => $payload,
            ]);
        }

        return response()->json($bill);
    });
}



    // DELETE /api/open-bills/{id}
    // dipanggil kalau bill sudah di-issue jadi Order / dibatalkan
    // public function destroy(Request $request, int $id)
    // {
    //     $user = null;

    //     $bill = OpenBill::where('id', $id)
    //         ->where('user_id', $user?->id)
    //         ->firstOrFail();

    //     $bill->delete();

    //     return response()->json(['ok' => true]);
    // }

    // dipanggil kalau bill sudah di-issue jadi Order / dibatalkan
public function destroy(Request $request, int $id)
{
    $user = null; // TODO: nanti bisa diisi dari auth kalau sudah jalan

    // HAPUS filter user_id dulu, supaya id-nya tetap bisa dihapus
    $bill = OpenBill::find($id);

    // Kalau sudah tidak ada, anggap saja sudah beres (idempotent)
    if (!$bill) {
        return response()->json(['ok' => true]);
    }

    // (opsional) kalau nanti kamu mau pakai user, baru dicek di sini:
    // if ($user && $bill->user_id !== $user->id) {
    //     abort(403, 'Forbidden');
    // }

    $bill->delete();

    return response()->json(['ok' => true]);
}

}
