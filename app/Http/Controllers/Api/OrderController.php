<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\StockMove;
use App\Models\Refund;
use App\Models\RefundItem;
use App\Services\DiscountResolver;
use App\Services\StockReductionService;

class OrderController extends BaseApiController
{
    public function __construct(
        private StockReductionService $stock,
        private DiscountResolver $discountResolver,
    ) {}

    public function store(Request $r)
    {
        $this->mergeNormalizedInput($r);

        $data = $r->validate([
            'items' => 'required|array|min:1',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.price_rupiah' => 'required|integer|min:0',
            'items.*.sku' => 'nullable|string',
            'items.*.menu_code' => 'nullable|string',
            'items.*.bundle_code' => 'nullable|string',
            'items.*.name' => 'nullable|string',
            'items.*.temp' => 'nullable|in:ice,hot',
            'items.*.size' => 'nullable|in:S,M,L',
            'tax_rate' => 'nullable|numeric',
            'service_rate'=> 'nullable|numeric',
            'payments' => 'array',
            'payments.*.method' => 'required|string|max:50',
            'payments.*.amount_rupiah' => 'required|integer|min:0',
            'discount' => 'nullable|array',
            'order_type' => 'nullable|string|max:50',
        ]);

        $uId = $this->currentUser($r)?->id;

        $subtotal = collect($data['items'])->sum(fn($it) => $it['price_rupiah'] * $it['qty']);
        [$discountRupiah, $discountMeta] = $this->discountResolver->resolve($data['discount'] ?? null, $subtotal);

        // $taxRate = $data['tax_rate'] ?? 0;
        // $tax = (int) round(($subtotal - $discountRupiah) * $taxRate);
        // $total = ($subtotal - $discountRupiah) + $tax;


        $netSubtotal = $subtotal - $discountRupiah;

        $taxRate = (float) ($data['tax_rate'] ?? 0);
        $serviceRate = (float) ($data['service_rate']?? 0);
        $serviceRupiah = (int) round($netSubtotal * $serviceRate);
        $totalService = $netSubtotal + $serviceRupiah;

        $tax = (int) round($totalService * $taxRate);

        $total = $totalService + $tax;


        $orderType = $data['order_type'] ?? ($data['sales_type'] ?? null) ?? ($data['type'] ?? null);

        $order = DB::transaction(function () use ($data, $uId, $subtotal, $discountRupiah, $serviceRupiah, $tax, $total, $orderType) {
            $stockCtx = $this->stock->preload($data['items']);

            $order = Order::create([
                'subtotal_rupiah' => $subtotal,
                'discount_rupiah' => $discountRupiah,
                'service_rupiah'  => $serviceRupiah,
                'tax_rupiah'      => $tax,
                'total_rupiah'    => $total,
                'payload'        => json_encode($data),
                'created_by'     => $uId,
                'order_type'     => $orderType,
            ]);

            $orderItemRows = [];
            $stockMoves = [];

            foreach ($data['items'] as $it) {
                $orderItemRows[] = [
                    'order_id'    => $order->id,
                    'sku'         => $it['sku'] ?? null,
                    'menu_code'   => $it['menu_code'] ?? null,
                    'bundle_code' => $it['bundle_code'] ?? null,
                    'qty'         => $it['qty'],
                    'price_rupiah' => $it['price_rupiah'],
                    'name'        => $it['name'] ?? null,
                    'temp'        => $it['temp'] ?? null,
                    'size'        => $it['size'] ?? null,
                    'created_by'  => $uId,
                ];

                $this->stock->reduce($it, (int) $it['qty'], $stockCtx, $stockMoves, $uId);
            }

            if ($orderItemRows !== []) {
                OrderItem::insert($orderItemRows);
            }

            if ($stockMoves !== []) {
                StockMove::insert($stockMoves);
            }

            if (! empty($data['payments'])) {
                $paymentRows = [];
                foreach ($data['payments'] as $p) {
                    $paymentRows[] = [
                        'order_id'     => $order->id,
                        'method'       => $p['method'],
                        'amount_rupiah' => (int) $p['amount_rupiah'],
                        'created_by'   => $uId,
                    ];
                }
                Payment::insert($paymentRows);
            }

            return $order;
        });

        return response()->json([
            'ok'          => true,
            'id'          => $order->id,
            'total_rupiah' => $order->total_rupiah,
        ]);
    }

    public function index(Request $r)
    {
        $u = $this->currentUser($r);
        $since = $r->query('since');

        $q = Order::query();

        if ($u) {
            $q->where('created_by', $u->id);
        }

        if (! empty($since)) {
            $q->where('created_at', '>=', $since);
        }

        $limit = max(1, min(
            (int) $r->query('limit', config('pos.limits.orders_default', 500)),
            config('pos.limits.orders_max', 2000)
        ));
        $q->limit($limit);

        $q->addSelect([
            'refunds_count' => Refund::selectRaw('COUNT(*)')
                ->whereColumn('order_id', 'orders.id'),
            'refund_total_rupiah' => Refund::selectRaw('COALESCE(SUM(total_rupiah),0)')
                ->whereColumn('order_id', 'orders.id'),
        ]);

        $columns = [
            'id',
            'subtotal_rupiah',
            'discount_rupiah',
            'service_rupiah',
            'tax_rupiah',
            'total_rupiah',
            'order_type',
            'created_at',
            'refunds_count',
            'refund_total_rupiah',
        ];

        if ($r->boolean('include_payload')) {
            $columns[] = 'payload';
        }

        return $q->orderByDesc('id')->get($columns);
    }

    public function show(Request $r, $id)
    {
        $u = $this->currentUser($r);
        $o = Order::with(['items', 'payments'])->findOrFail($id);

        if ($u && $o->created_by && $o->created_by !== $u->id) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }

        $refundedAgg = RefundItem::where('order_id', $o->id)
            ->groupBy('order_item_id')
            ->select('order_item_id', DB::raw('COALESCE(SUM(qty),0) as refunded_qty'))
            ->pluck('refunded_qty', 'order_item_id');

        $refundStats = Refund::where('order_id', $o->id)
            ->selectRaw('COUNT(*) as refunds_count, COALESCE(SUM(total_rupiah),0) as refund_total_rupiah')
            ->first();

        $items = $o->items->map(function ($it) use ($refundedAgg) {
            $rid = (int) ($refundedAgg[$it->id] ?? 0);
            $it->setAttribute('refunded_qty', $rid);
            $it->setAttribute('refunded', $rid > 0);

            return $it;
        });

        return [
            'ok' => true,
            'order' => $o,
            'items' => $items,
            'payments' => $o->payments,
            'has_refund' => (int) $refundStats->refunds_count > 0,
            'refunds_count' => (int) $refundStats->refunds_count,
            'refund_total_rupiah' => (int) $refundStats->refund_total_rupiah,
        ];
    }

    // ===== daftar refund + itemnya (tetap) =====
    public function refunds(Request $r, $id)
    {
        Order::findOrFail($id);

        return [
            'ok' => true,
            'refunds' => Refund::with('items')
                ->where('order_id', $id)
                ->orderByDesc('id')
                ->get(),
        ];
    }

    // ===== Buat refund parsial/total =====
    // public function refund(Request $r, $id)
    // {
    //     $data = $r->validate([
    //         'items' => 'required|array|min:1',
    //         'items.*.order_item_id'    => 'nullable|integer',
    //         'items.*.sku'              => 'nullable|string',
    //         'items.*.menu_code'        => 'nullable|string',
    //         'items.*.qty'              => 'required|integer|min:1',
    //         'items.*.unit_price_rupiah' => 'nullable|integer|min:0',
    //         'reason' => 'nullable|string|max:200',
    //     ]);

    //     $order = Order::with('items')->findOrFail($id);
    //     $orderItems = $order->items->keyBy('id');

    //     // total qty yang sudah direfund per order_item
    //     $refundedSoFar = RefundItem::where('order_id',$order->id)
    //         ->selectRaw('order_item_id, COALESCE(SUM(qty),0) as qty')
    //         ->groupBy('order_item_id')
    //         ->pluck('qty','order_item_id');

    //     // Validasi qty ≤ (dibeli - sudah_refund)
    //     foreach ($data['items'] as $it) {
    //         $oiId = $it['order_item_id'] ?? null;
    //         if ($oiId && isset($orderItems[$oiId])) {
    //             $bought  = (int)$orderItems[$oiId]->qty;
    //             $already = (int)($refundedSoFar[$oiId] ?? 0);
    //             $req     = (int)$it['qty'];
    //             if ($req > max(0, $bought - $already)) {
    //                 return response()->json(['ok'=>false,'message'=>"refund qty exceeds purchased for item $oiId"], 422);
    //             }
    //         }
    //     }

    //     // // Hitung total refund (fallback ambil harga dari order_item)
    //     // $total = 0;
    //     // foreach ($data['items'] as &$it) {
    //     //     $unit = (int)($it['unit_price_rupiah'] ?? 0);
    //     //     if (!$unit && !empty($it['order_item_id']) && isset($orderItems[$it['order_item_id']])){
    //     //         $unit = (int)($orderItems[$it['order_item_id']]->price_rupiah ?? $orderItems[$it['order_item_id']]->price ?? 0);
    //     //     }
    //     //     $it['unit_price_rupiah'] = $unit;
    //     //     $total += $unit * (int)$it['qty'];
    //     // }
    //     // unset($it);

    //     // $refund = DB::transaction(function () use ($data, $order, $total, $orderItems) {

    //     //     $refund = Refund::create([
    //     //         'order_id'    => $order->id,
    //     //         'total_rupiah' => $total,
    //     //         'reason'      => $data['reason'] ?? null,
    //     //         'payload'     => $data,
    //     //     ]);

    //             // Hitung subtotal refund (fallback ambil harga dari order_item)
    //     $subtotalRefund = 0;
    //     foreach ($data['items'] as &$it) {
    //         $unit = (int)($it['unit_price_rupiah'] ?? 0);

    //         if (
    //             !$unit &&
    //             !empty($it['order_item_id']) &&
    //             isset($orderItems[$it['order_item_id']])
    //         ) {
    //             $oi   = $orderItems[$it['order_item_id']];
    //             $unit = (int)($oi->price_rupiah ?? $oi->price ?? 0);
    //         }

    //         $it['unit_price_rupiah'] = $unit;
    //         $subtotalRefund += $unit * (int)$it['qty'];
    //     }
    //     unset($it);

    //     // ===== Distribusi diskon & pajak proporsional dari order asli =====
    //     $orderSubtotal = (int)($order->subtotal_rupiah ?? 0);
    //     $orderDiscount = (int)($order->discount_rupiah ?? 0);
    //     $orderTax      = (int)($order->tax_rupiah ?? 0);

    //     $ratio = 0;
    //     if ($orderSubtotal > 0 && $subtotalRefund > 0) {
    //         // batasi max 1 biar ga lebih gede dari order asli
    //         $ratio = min(1, $subtotalRefund / $orderSubtotal);
    //     }

    //     $discountRefund = (int) round($orderDiscount * $ratio);
    //     $taxRefund      = (int) round($orderTax * $ratio);

    //     // total refund = subtotal - bagian diskon + bagian pajak
    //     $totalRefund = $subtotalRefund - $discountRefund + $taxRefund;

    //     // simpan perhitungan di payload buat debugging
    //     $calc = [
    //         'subtotal_refund_rupiah' => $subtotalRefund,
    //         'discount_refund_rupiah' => $discountRefund,
    //         'tax_refund_rupiah'      => $taxRefund,
    //         'ratio'                 => $ratio,
    //     ];

    //     $refund = DB::transaction(function () use ($data, $order, $totalRefund, $orderItems, $calc) {

    //         $payload = $data;
    //         $payload['_calc'] = $calc;

    //         $refund = Refund::create([
    //             'order_id'    => $order->id,
    //             'total_rupiah' => $totalRefund,
    //             'reason'      => $data['reason'] ?? null,
    //             'payload'     => $payload,
    //         ]);


    //         // helper: tambah stok + log
    //         $restockSku = function (string $sku, int $inc) {
    //             $p = Product::where('sku', $sku)->first();
    //             if ($p && $inc > 0) {
    //                 $p->increment('stock', $inc);
    //                 StockMove::create([
    //                     'sku'    => $p->sku,
    //                     'delta'  => +$inc,
    //                     'reason' => 'refund',
    //                 ]);
    //             }
    //         };

    //         foreach ($data['items'] as $it) {
    //             RefundItem::create([
    //                 'refund_id'        => $refund->id,
    //                 'order_id'         => $order->id,
    //                 'order_item_id'    => $it['order_item_id'] ?? null,
    //                 'sku'              => $it['sku'] ?? null,
    //                 'menu_code'        => $it['menu_code'] ?? null,
    //                 'qty'              => (int)$it['qty'],
    //                 'unit_price_rupiah' => (int)$it['unit_price_rupiah'],
    //             ]);

    //             // === RESTOCK ===
    //             if (!empty($it['sku'])) {
    //                 $restockSku($it['sku'], (int)$it['qty']);
    //             } elseif (!empty($it['menu_code'])) {
    //                 $components = MenuItem::where('menu_code', $it['menu_code'])->get(['product_sku','qty']);
    //                 foreach ($components as $c) {
    //                     $restockSku($c->product_sku, (int)$c->qty * (int)$it['qty']);
    //                 }
    //             } else {
    //                 // fallback: lewat order_item
    //                 $oi = !empty($it['order_item_id']) ? ($orderItems[$it['order_item_id']] ?? null) : null;
    //                 if ($oi && !empty($oi->sku)) {
    //                     $restockSku($oi->sku, (int)$it['qty']);
    //                 } elseif ($oi && !empty($oi->menu_code)) {
    //                     $components = MenuItem::where('menu_code', $oi->menu_code)->get(['product_sku','qty']);
    //                     foreach ($components as $c) {
    //                         $restockSku($c->product_sku, (int)$c->qty * (int)$it['qty']);
    //                     }
    //                 }
    //             }
    //         }

    //         return $refund;
    //     });

    //     return ['ok'=>true, 'refund_id'=>$refund->id, 'total_rupiah'=>$refund->total_rupiah];
    // }


    public function refund(Request $r, $id)
{
    $this->mergeNormalizedInput($r);

    $data = $r->validate([
        'items' => 'required|array|min:1',
        'items.*.order_item_id'    => 'nullable|integer',
        'items.*.sku'              => 'nullable|string',
        'items.*.menu_code'        => 'nullable|string',
        'items.*.bundle_code'      => 'nullable|string',
        'items.*.qty'              => 'required|integer|min:1',
        'items.*.unit_price_rupiah' => 'nullable|integer|min:0',
        'items.*.name'             => 'nullable|string', // TAMBAHAN: nama item
        'reason' => 'nullable|string|max:200',
        'adjust_payments' => 'nullable|boolean', // TAMBAHAN: flag untuk adjust payment
    ]);

    $order = Order::with(['items', 'payments'])->findOrFail($id);

    $isPaid = $order->payments->sum('amount_rupiah') >= $order->total_rupiah;

    $orderItems = $order->items->keyBy('id');

    // total qty yang sudah direfund per order_item
    $refundedSoFar = RefundItem::where('order_id',$order->id)
        ->selectRaw('order_item_id, COALESCE(SUM(qty),0) as qty')
        ->groupBy('order_item_id')
        ->pluck('qty','order_item_id');

    // Validasi qty ≤ (dibeli - sudah_refund)
    foreach ($data['items'] as $it) {
        $oiId = $it['order_item_id'] ?? null;
        if ($oiId && isset($orderItems[$oiId])) {
            $bought  = (int)$orderItems[$oiId]->qty;
            $already = (int)($refundedSoFar[$oiId] ?? 0);
            $req     = (int)$it['qty'];
            if ($req > max(0, $bought - $already)) {
                return response()->json(['ok'=>false,'message'=>"refund qty exceeds purchased for item $oiId"], 422);
            }
        }
    }

    // Hitung subtotal refund
    $subtotalRefund = 0;
    $refundItemsDetails = []; // Untuk menyimpan detail item yang direfund
    
    foreach ($data['items'] as &$it) {
        $unit = (int)($it['unit_price_rupiah'] ?? 0);
        $name = $it['name'] ?? '';

        if (!$unit && !empty($it['order_item_id']) && isset($orderItems[$it['order_item_id']])) {
            $oi = $orderItems[$it['order_item_id']];
            $unit = (int)($oi->price_rupiah ?? $oi->price ?? 0);
            $name = $name ?: ($oi->name ?? '');
        }

        $it['unit_price_rupiah'] = $unit;
        $it['name'] = $name;
        
        $itemTotal = $unit * (int)$it['qty'];
        $subtotalRefund += $itemTotal;
        
        // Simpan detail untuk payload
        $refundItemsDetails[] = [
            'order_item_id' => $it['order_item_id'] ?? null,
            'sku' => $it['sku'] ?? null,
            'menu_code' => $it['menu_code'] ?? null,
            'name' => $name,
            'qty' => (int)$it['qty'],
            'unit_price_rupiah' => $unit,
            'item_total_rupiah' => $itemTotal,
        ];
    }
    unset($it);

    // ===== Distribusi diskon & pajak proporsional =====
    $orderSubtotal = (int)($order->subtotal_rupiah ?? 0);
    $orderDiscount = (int)($order->discount_rupiah ?? 0);
    $orderTax      = (int)($order->tax_rupiah ?? 0);

    $ratio = 0;
    if ($orderSubtotal > 0 && $subtotalRefund > 0) {
        $ratio = min(1, $subtotalRefund / $orderSubtotal);
    }

    $discountRefund = (int) round($orderDiscount * $ratio);
    $taxRefund      = (int) round($orderTax * $ratio);

    // total refund = subtotal - bagian diskon + bagian pajak
    $totalRefund = $subtotalRefund - $discountRefund + $taxRefund;

    $calc = [
        'subtotal_refund_rupiah' => $subtotalRefund,
        'discount_refund_rupiah' => $discountRefund,
        'tax_refund_rupiah'      => $taxRefund,
        'ratio'                 => $ratio,
        'total_refund_rupiah'    => $totalRefund,
    ];

    $stockCtx = $this->stock->preload($data['items'], $orderItems);

    $refund = DB::transaction(function () use ($data, $order, $totalRefund, $orderItems, $calc, $refundItemsDetails, $isPaid, $stockCtx) {
        $payload = [
            'items' => $refundItemsDetails,
            'reason' => $data['reason'] ?? null,
            'adjust_payments' => $data['adjust_payments'] ?? false,
            '_calc' => $calc,
        ];

        $refund = Refund::create([
            'order_id'    => $order->id,
            'total_rupiah' => $totalRefund,
            'reason'      => $data['reason'] ?? null,
            'payload'     => $payload,
        ]);

        $stockMoves = [];
        $refundItemRows = [];
        $now = now();

        foreach ($data['items'] as $it) {
            $refundItemRows[] = [
                'refund_id'        => $refund->id,
                'order_id'         => $order->id,
                'order_item_id'    => $it['order_item_id'] ?? null,
                'sku'              => $it['sku'] ?? null,
                'menu_code'        => $it['menu_code'] ?? null,
                'name'             => $it['name'] ?? null,
                'qty'              => (int) $it['qty'],
                'unit_price_rupiah' => (int) $it['unit_price_rupiah'],
                'created_at'       => $now,
                'updated_at'       => $now,
            ];

            $this->stock->restock($it, (int) $it['qty'], $stockCtx, $stockMoves, $orderItems);
        }

        if ($refundItemRows !== []) {
            RefundItem::insert($refundItemRows);
        }

        if ($stockMoves !== []) {
            StockMove::insert($stockMoves);
        }

        // Update total refund di order
        $order->increment('refund_total_rupiah', $totalRefund);

        // ===== Adjust Payments jika diminta dan order sudah dibayar =====
        if (($data['adjust_payments'] ?? false) && $isPaid) {
            $this->adjustPaymentsAfterRefund($order, $totalRefund);
        }

        return $refund;
    });

    $order->refresh();
    $paidRupiah = (int) Payment::where('order_id', $order->id)->sum('amount_rupiah');

    return [
        'ok' => true,
        'refund_id' => $refund->id,
        'total_rupiah' => $refund->total_rupiah,
        'order_refund_total_rupiah' => (int) $order->refund_total_rupiah,
        'remaining_balance_rupiah' => $paidRupiah - (int) $order->refund_total_rupiah,
    ];
}

    private function adjustPaymentsAfterRefund(Order $order, int $refundAmount): void
    {
        $payments = $order->relationLoaded('payments')
            ? $order->payments->sortByDesc('id')->values()
            : $order->payments()->orderByDesc('id')->get();

        $remainingRefund = $refundAmount;

        foreach ($payments as $payment) {
            if ($remainingRefund <= 0) {
                break;
            }

            $available = (int) $payment->amount_rupiah;
            $toRefund = min($available, $remainingRefund);

            if ($toRefund <= 0) {
                continue;
            }

            $payment->decrement('amount_rupiah', $toRefund);

            if ($payment->amount_rupiah <= 0) {
                $payment->delete();
            }

            $remainingRefund -= $toRefund;
        }

        if ($remainingRefund > 0) {
            Payment::create([
                'order_id' => $order->id,
                'method' => 'refund_adjustment',
                'amount_rupiah' => -$remainingRefund,
                'created_by' => auth()->id(),
                'created_at' => now(),
            ]);
        }
    }
}
