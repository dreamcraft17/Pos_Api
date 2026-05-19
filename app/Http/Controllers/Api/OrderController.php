<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\MenuItem;
use App\Models\StockMove;
use App\Models\Discount;
use App\Models\Refund;
use App\Models\RefundItem;

class OrderController extends BaseApiController
{
    /** @return array{0: \Illuminate\Support\Collection<string, Product>, 1: \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, MenuItem>>} */
    private function preloadStockContext(array $items): array
    {
        $skus = [];
        $menuCodes = [];

        foreach ($items as $it) {
            if (! empty($it['sku'])) {
                $skus[] = $it['sku'];
            } elseif (! empty($it['menu_code'])) {
                $menuCodes[] = $it['menu_code'];
            }
        }

        $menuComponents = MenuItem::whereIn('menu_code', array_unique($menuCodes))
            ->get(['menu_code', 'product_sku', 'qty'])
            ->groupBy('menu_code');

        foreach ($menuComponents as $components) {
            foreach ($components as $c) {
                $skus[] = $c->product_sku;
            }
        }

        $products = Product::whereIn('sku', array_unique(array_filter($skus)))->get()->keyBy('sku');

        return [$products, $menuComponents];
    }

    /** @param \Illuminate\Support\Collection<int, OrderItem> $orderItems */
    private function preloadRefundStockContext(array $items, $orderItems): array
    {
        $skus = [];
        $menuCodes = [];

        foreach ($items as $it) {
            if (! empty($it['sku'])) {
                $skus[] = $it['sku'];
            } elseif (! empty($it['menu_code'])) {
                $menuCodes[] = $it['menu_code'];
            } elseif (! empty($it['order_item_id'])) {
                $oi = $orderItems[$it['order_item_id']] ?? null;
                if ($oi?->sku) {
                    $skus[] = $oi->sku;
                } elseif ($oi?->menu_code) {
                    $menuCodes[] = $oi->menu_code;
                }
            }
        }

        $menuComponents = MenuItem::whereIn('menu_code', array_unique($menuCodes))
            ->get(['menu_code', 'product_sku', 'qty'])
            ->groupBy('menu_code');

        foreach ($menuComponents as $components) {
            foreach ($components as $c) {
                $skus[] = $c->product_sku;
            }
        }

        $products = Product::whereIn('sku', array_unique(array_filter($skus)))->get()->keyBy('sku');

        return [$products, $menuComponents];
    }

    private function applyStockReduction(
        array $it,
        int $qtyMultiplier,
        $products,
        $menuComponents,
        ?int $uId,
        array &$stockMoves
    ): void {
        if (! empty($it['sku'])) {
            $p = $products[$it['sku']] ?? null;
            if ($p) {
                $reduce = $qtyMultiplier;
                $p->decrement('stock', $reduce);
                $stockMoves[] = [
                    'sku'        => $p->sku,
                    'delta'      => -$reduce,
                    'reason'     => 'order direct item',
                    'created_by' => $uId,
                ];
            }

            return;
        }

        if (empty($it['menu_code'])) {
            return;
        }

        foreach ($menuComponents[$it['menu_code']] ?? [] as $c) {
            $p = $products[$c->product_sku] ?? null;
            if (! $p) {
                continue;
            }
            $reduceBy = $c->qty * $qtyMultiplier;
            $p->decrement('stock', $reduceBy);
            $stockMoves[] = [
                'sku'        => $p->sku,
                'delta'      => -$reduceBy,
                'reason'     => 'order menu component',
                'created_by' => $uId,
            ];
        }
    }

    private function resolveDiscount(?array $d, int $subtotal): array
    {
        if (!$d) return [0, null];
        $payload = $d;
        if (!empty($d['code'])) {
            $found = Discount::where('code',$d['code'])->first();
            if ($found && $found->enabled) {
                $payload = ['code'=>$found->code,'kind'=>$found->kind,'value'=>$found->value];
                if ($found->kind === 'percent') {
                    $off = (int) round($subtotal * (float)$found->value / 100.0);
                    return [min($subtotal, $off), $payload];
                } else {
                    return [min($subtotal, (int)round($found->value)), $payload];
                }
            }
        }
        if (!empty($d['kind']) && isset($d['value'])) {
            if ($d['kind'] === 'percent') {
                $off = (int) round($subtotal * (float)$d['value'] / 100.0);
                return [min($subtotal, $off), $payload];
            } else {
                return [min($subtotal, (int)round($d['value'])), $payload];
            }
        }
        return [0, null];
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'items' => 'required|array|min:1',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.price_cents' => 'required|integer|min:0',
            'items.*.sku' => 'nullable|string',
            'items.*.menu_code' => 'nullable|string',
            'items.*.name' => 'nullable|string',
            'items.*.temp' => 'nullable|in:ice,hot',
            'items.*.size' => 'nullable|in:S,M,L',
            'tax_rate' => 'nullable|numeric',
            'service_rate'=> 'nullable|numeric',
            'payments' => 'array',
            'payments.*.method' => 'required|string|max:50',
            'payments.*.amount_cents' => 'required|integer|min:0',
            'discount' => 'nullable|array',
            'order_type' => 'nullable|string|max:50',
        ]);

        $uId = $this->currentUser($r)?->id;

        $subtotal = collect($data['items'])->sum(fn($it) => $it['price_cents'] * $it['qty']);
        [$discountCents, $discountMeta] = $this->resolveDiscount($data['discount'] ?? null, $subtotal);

        // $taxRate = $data['tax_rate'] ?? 0;
        // $tax = (int) round(($subtotal - $discountCents) * $taxRate);
        // $total = ($subtotal - $discountCents) + $tax;


        $netSubtotal = $subtotal - $discountCents;

        $taxRate = (float) ($data['tax_rate'] ?? 0);
        $serviceRate = (float) ($data['service_rate']?? 0);
        $serviceCents = (int) round($netSubtotal * $serviceRate);
        $totalService = $netSubtotal + $serviceCents;

        $tax = (int) round($totalService * $taxRate);

        $total = $totalService + $tax;


        $orderType = $data['order_type'] ?? ($data['sales_type'] ?? null) ?? ($data['type'] ?? null);

        $order = DB::transaction(function () use ($data, $uId, $subtotal, $discountCents, $serviceCents, $tax, $total, $orderType) {
            [$products, $menuComponents] = $this->preloadStockContext($data['items']);

            $order = Order::create([
                'subtotal_cents' => $subtotal,
                'discount_cents' => $discountCents,
                'service_cents'  => $serviceCents,
                'tax_cents'      => $tax,
                'total_cents'    => $total,
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
                    'qty'         => $it['qty'],
                    'price_cents' => $it['price_cents'],
                    'name'        => $it['name'] ?? null,
                    'temp'        => $it['temp'] ?? null,
                    'size'        => $it['size'] ?? null,
                    'created_by'  => $uId,
                ];

                $this->applyStockReduction($it, (int) $it['qty'], $products, $menuComponents, $uId, $stockMoves);
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
                        'amount_cents' => (int) $p['amount_cents'],
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
            'total_cents' => $order->total_cents,
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

        $limit = max(1, min((int) $r->query('limit', 500), 2000));
        $q->limit($limit);

        $q->addSelect([
            'refunds_count' => Refund::selectRaw('COUNT(*)')
                ->whereColumn('order_id', 'orders.id'),
            'refund_total_cents' => Refund::selectRaw('COALESCE(SUM(total_cents),0)')
                ->whereColumn('order_id', 'orders.id'),
        ]);

        return $q->orderByDesc('id')->get([
            'id',
            'subtotal_cents',
            'discount_cents',
            'service_cents',
            'tax_cents',
            'total_cents',
            'order_type',
            'payload',
            'created_at',
            // kolom subselect juga ikut terambil:
            'refunds_count',
            'refund_total_cents',
        ]);
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
            ->selectRaw('COUNT(*) as refunds_count, COALESCE(SUM(total_cents),0) as refund_total_cents')
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
            'refund_total_cents' => (int) $refundStats->refund_total_cents,
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
    //         'items.*.unit_price_cents' => 'nullable|integer|min:0',
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
    //     //     $unit = (int)($it['unit_price_cents'] ?? 0);
    //     //     if (!$unit && !empty($it['order_item_id']) && isset($orderItems[$it['order_item_id']])){
    //     //         $unit = (int)($orderItems[$it['order_item_id']]->price_cents ?? $orderItems[$it['order_item_id']]->price ?? 0);
    //     //     }
    //     //     $it['unit_price_cents'] = $unit;
    //     //     $total += $unit * (int)$it['qty'];
    //     // }
    //     // unset($it);

    //     // $refund = DB::transaction(function () use ($data, $order, $total, $orderItems) {

    //     //     $refund = Refund::create([
    //     //         'order_id'    => $order->id,
    //     //         'total_cents' => $total,
    //     //         'reason'      => $data['reason'] ?? null,
    //     //         'payload'     => $data,
    //     //     ]);

    //             // Hitung subtotal refund (fallback ambil harga dari order_item)
    //     $subtotalRefund = 0;
    //     foreach ($data['items'] as &$it) {
    //         $unit = (int)($it['unit_price_cents'] ?? 0);

    //         if (
    //             !$unit &&
    //             !empty($it['order_item_id']) &&
    //             isset($orderItems[$it['order_item_id']])
    //         ) {
    //             $oi   = $orderItems[$it['order_item_id']];
    //             $unit = (int)($oi->price_cents ?? $oi->price ?? 0);
    //         }

    //         $it['unit_price_cents'] = $unit;
    //         $subtotalRefund += $unit * (int)$it['qty'];
    //     }
    //     unset($it);

    //     // ===== Distribusi diskon & pajak proporsional dari order asli =====
    //     $orderSubtotal = (int)($order->subtotal_cents ?? 0);
    //     $orderDiscount = (int)($order->discount_cents ?? 0);
    //     $orderTax      = (int)($order->tax_cents ?? 0);

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
    //         'subtotal_refund_cents' => $subtotalRefund,
    //         'discount_refund_cents' => $discountRefund,
    //         'tax_refund_cents'      => $taxRefund,
    //         'ratio'                 => $ratio,
    //     ];

    //     $refund = DB::transaction(function () use ($data, $order, $totalRefund, $orderItems, $calc) {

    //         $payload = $data;
    //         $payload['_calc'] = $calc;

    //         $refund = Refund::create([
    //             'order_id'    => $order->id,
    //             'total_cents' => $totalRefund,
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
    //                 'unit_price_cents' => (int)$it['unit_price_cents'],
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

    //     return ['ok'=>true, 'refund_id'=>$refund->id, 'total_cents'=>$refund->total_cents];
    // }


    public function refund(Request $r, $id)
{
    $data = $r->validate([
        'items' => 'required|array|min:1',
        'items.*.order_item_id'    => 'nullable|integer',
        'items.*.sku'              => 'nullable|string',
        'items.*.menu_code'        => 'nullable|string',
        'items.*.qty'              => 'required|integer|min:1',
        'items.*.unit_price_cents' => 'nullable|integer|min:0',
        'items.*.name'             => 'nullable|string', // TAMBAHAN: nama item
        'reason' => 'nullable|string|max:200',
        'adjust_payments' => 'nullable|boolean', // TAMBAHAN: flag untuk adjust payment
    ]);

    $order = Order::with(['items', 'payments'])->findOrFail($id);

    $isPaid = $order->payments->sum('amount_cents') >= $order->total_cents;

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
        $unit = (int)($it['unit_price_cents'] ?? 0);
        $name = $it['name'] ?? '';

        if (!$unit && !empty($it['order_item_id']) && isset($orderItems[$it['order_item_id']])) {
            $oi = $orderItems[$it['order_item_id']];
            $unit = (int)($oi->price_cents ?? $oi->price ?? 0);
            $name = $name ?: ($oi->name ?? '');
        }

        $it['unit_price_cents'] = $unit;
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
            'unit_price_cents' => $unit,
            'item_total_cents' => $itemTotal,
        ];
    }
    unset($it);

    // ===== Distribusi diskon & pajak proporsional =====
    $orderSubtotal = (int)($order->subtotal_cents ?? 0);
    $orderDiscount = (int)($order->discount_cents ?? 0);
    $orderTax      = (int)($order->tax_cents ?? 0);

    $ratio = 0;
    if ($orderSubtotal > 0 && $subtotalRefund > 0) {
        $ratio = min(1, $subtotalRefund / $orderSubtotal);
    }

    $discountRefund = (int) round($orderDiscount * $ratio);
    $taxRefund      = (int) round($orderTax * $ratio);

    // total refund = subtotal - bagian diskon + bagian pajak
    $totalRefund = $subtotalRefund - $discountRefund + $taxRefund;

    $calc = [
        'subtotal_refund_cents' => $subtotalRefund,
        'discount_refund_cents' => $discountRefund,
        'tax_refund_cents'      => $taxRefund,
        'ratio'                 => $ratio,
        'total_refund_cents'    => $totalRefund,
    ];

    [$products, $menuComponents] = $this->preloadRefundStockContext($data['items'], $orderItems);

    $refund = DB::transaction(function () use ($data, $order, $totalRefund, $orderItems, $calc, $refundItemsDetails, $isPaid, $products, $menuComponents) {
        $payload = [
            'items' => $refundItemsDetails,
            'reason' => $data['reason'] ?? null,
            'adjust_payments' => $data['adjust_payments'] ?? false,
            '_calc' => $calc,
        ];

        $refund = Refund::create([
            'order_id'    => $order->id,
            'total_cents' => $totalRefund,
            'reason'      => $data['reason'] ?? null,
            'payload'     => $payload,
        ]);

        $stockMoves = [];

        $restockSku = function (string $sku, int $inc) use ($products, &$stockMoves) {
            if ($inc <= 0) {
                return;
            }
            $p = $products[$sku] ?? null;
            if (! $p) {
                return;
            }
            $p->increment('stock', $inc);
            $stockMoves[] = [
                'sku'    => $p->sku,
                'delta'  => $inc,
                'reason' => 'refund',
            ];
        };

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
                'unit_price_cents' => (int) $it['unit_price_cents'],
                'created_at'       => $now,
                'updated_at'       => $now,
            ];

            if (! empty($it['sku'])) {
                $restockSku($it['sku'], (int) $it['qty']);
            } elseif (! empty($it['menu_code'])) {
                foreach ($menuComponents[$it['menu_code']] ?? [] as $c) {
                    $restockSku($c->product_sku, (int) $c->qty * (int) $it['qty']);
                }
            } else {
                $oi = ! empty($it['order_item_id']) ? ($orderItems[$it['order_item_id']] ?? null) : null;
                if ($oi && ! empty($oi->sku)) {
                    $restockSku($oi->sku, (int) $it['qty']);
                } elseif ($oi && ! empty($oi->menu_code)) {
                    foreach ($menuComponents[$oi->menu_code] ?? [] as $c) {
                        $restockSku($c->product_sku, (int) $c->qty * (int) $it['qty']);
                    }
                }
            }
        }

        if ($refundItemRows !== []) {
            RefundItem::insert($refundItemRows);
        }

        if ($stockMoves !== []) {
            StockMove::insert($stockMoves);
        }

        // Update total refund di order
        $order->increment('refund_total_cents', $totalRefund);

        // ===== Adjust Payments jika diminta dan order sudah dibayar =====
        if (($data['adjust_payments'] ?? false) && $isPaid) {
            $this->adjustPaymentsAfterRefund($order, $totalRefund);
        }

        return $refund;
    });

    $order->refresh();
    $paidCents = (int) Payment::where('order_id', $order->id)->sum('amount_cents');

    return [
        'ok' => true,
        'refund_id' => $refund->id,
        'total_cents' => $refund->total_cents,
        'order_refund_total_cents' => (int) $order->refund_total_cents,
        'remaining_balance_cents' => $paidCents - (int) $order->refund_total_cents,
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

            $available = (int) $payment->amount_cents;
            $toRefund = min($available, $remainingRefund);

            if ($toRefund <= 0) {
                continue;
            }

            $payment->decrement('amount_cents', $toRefund);

            if ($payment->amount_cents <= 0) {
                $payment->delete();
            }

            $remainingRefund -= $toRefund;
        }

        if ($remainingRefund > 0) {
            Payment::create([
                'order_id' => $order->id,
                'method' => 'refund_adjustment',
                'amount_cents' => -$remainingRefund,
                'created_by' => auth()->id(),
                'created_at' => now(),
            ]);
        }
    }
}
