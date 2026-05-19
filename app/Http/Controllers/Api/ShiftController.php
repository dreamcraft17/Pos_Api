<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Shift;
use App\Models\Order;

class ShiftController extends BaseApiController
{
    // ✅ Cache duration untuk active summary (10 detik)
    private const CACHE_TTL = 10;

    public function index(Request $r)
    {
        $u = $this->currentUser($r);
        $q = Shift::query();

        if ($u) {
            $q->where('created_by', $u->id);
        }

        if ($r->filled('start_date')) {
            $q->where('start_at', '>=', $r->input('start_date'));
        }
        if ($r->filled('end_date')) {
            $q->where('end_at', '<=', $r->input('end_date'));
        }

        // ✅ Add pagination
        $perPage = min((int)$r->input('per_page', 20), 100);
        return $q->orderByDesc('end_at')->paginate($perPage);
    }

    public function publicIndex(Request $r)
    {
        $cacheKey = 'public_shifts_' . md5(json_encode($r->all()));
        
        return Cache::remember($cacheKey, 300, function () use ($r) {
            $q = Shift::query()->whereNotNull('end_at');

            if ($r->filled('start_date')) {
                $q->where('start_at', '>=', $r->input('start_date'));
            }
            if ($r->filled('end_date')) {
                $q->where('end_at', '<=', $r->input('end_date'));
            }
            if ($r->filled('outlet_name')) {
                $q->where('outlet_name', $r->input('outlet_name'));
            }

            $limit = max(1, min((int)$r->input('limit', 200), 500));

            $rows = $q->orderByDesc('end_at')
                ->limit($limit)
                ->get([
                    'id', 'start_at', 'end_at', 'opening_cash_cents',
                    'outlet_name', 'cashier_name', 'created_by',
                ]);

            return response()->json($rows);
        });
    }

    public function active(Request $r)
    {
        try {
            $u = $this->currentUser($r);
            $q = Shift::query()->whereNull('end_at');

            if ($u) {
                $q->where(function($w) use ($u) {
                    $w->where('created_by', $u->id)->orWhereNull('created_by');
                });
            } else {
                $q->whereNull('created_by');
            }

            $shift = $q->orderByDesc('start_at')->first();

            if (!$shift) {
                return response()->json(['ok' => true, 'active' => null]);
            }

            return response()->json([
                'ok' => true,
                'active' => [
                    'id' => $shift->id,
                    'start_at' => $shift->start_at,
                    'opening_cash_cents' => $shift->opening_cash_cents,
                    'outlet_name' => $shift->outlet_name,
                    'cashier_name' => $shift->cashier_name,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to check active shift', [
                'msg' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);
            return response()->json(['ok' => false, 'message' => 'server error'], 500);
        }
    }

    // ✅ SUPER OPTIMIZED: Caching + Single Query
    public function activeSummary(Request $r)
    {
        try {
            $u = $this->currentUser($r);
            $userId = $u?->id;

            // ✅ Cache key berdasarkan user (setiap user punya cache sendiri)
            $cacheKey = "active_shift_summary_" . ($userId ?? 'public');

            // ✅ Cache selama 10 detik (auto-refresh client juga 10 detik)
            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($u, $userId) {
                return $this->computeActiveSummary($u, $userId);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to build active shift summary', [
                'msg' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'ok' => false,
                'message' => 'server error',
            ], 500);
        }
    }

    // ✅ OPTIMIZED COMPUTATION: Semua query dalam satu hit
    private function computeActiveSummary($u, $userId)
    {
        // 1) Get active shift
        $q = Shift::query()->whereNull('end_at');

        if ($u) {
            $q->where(function ($w) use ($u) {
                $w->where('created_by', $u->id)->orWhereNull('created_by');
            });
        } else {
            $q->whereNull('created_by');
        }

        $shift = $q->orderByDesc('start_at')->first();

        if (!$shift) {
            return response()->json([
                'ok' => true,
                'active' => null,
                'summary' => null,
            ]);
        }

        // 2) Calculate time range
        $startAt = \Carbon\Carbon::parse($shift->start_at)->setTime(8, 0, 0);
        $endAt = now();

        if ($startAt->gt($endAt)) {
            $startAt = \Carbon\Carbon::today()->setTime(8, 0, 0);
        }

        $today = \Carbon\Carbon::today();

        // 3) ✅ SINGLE MEGA QUERY: Get everything in one shot
        $summary = DB::select("
            SELECT 
                -- Orders count (shift period)
                COUNT(DISTINCT o.id) as orders_count,
                
                -- Financial aggregates (shift period)
                COALESCE(SUM(o.subtotal_cents), 0) as gross_cents,
                COALESCE(SUM(o.discount_cents), 0) as discount_cents,
                COALESCE(SUM(o.tax_cents), 0) as tax_cents,
                COALESCE(SUM(o.total_cents), 0) as net_cents,
                
                -- Sold items today
                (
                    SELECT COUNT(*) 
                    FROM orders 
                    WHERE DATE(created_at) = CURDATE()
                    " . ($userId ? "AND created_by = ?" : "") . "
                ) as sold_items_today,
                
                -- Refund total (shift period)
                (
                    SELECT COALESCE(SUM(r.total_cents), 0)
                    FROM refunds r
                    WHERE r.order_id IN (
                        SELECT id FROM orders 
                        WHERE created_at BETWEEN ? AND ?
                        " . ($userId ? "AND created_by = ?" : "") . "
                    )
                ) as refund_cents
                
            FROM orders o
            WHERE o.created_at BETWEEN ? AND ?
            " . ($userId ? "AND o.created_by = ?" : ""),
            $this->buildQueryParams($userId, $startAt, $endAt, $today)
        );

        $data = $summary[0] ?? null;

        if (!$data || $data->orders_count == 0) {
            return response()->json([
                'ok' => true,
                'active' => [
                    'id' => $shift->id,
                    'start_at' => $startAt,
                    'opening_cash_cents' => $shift->opening_cash_cents,
                    'outlet_name' => $shift->outlet_name,
                    'cashier_name' => $shift->cashier_name,
                ],
                'summary' => [
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'orders_count' => 0,
                    'sold_items' => 0,
                    'gross_cents' => 0,
                    'discount_cents' => 0,
                    'tax_cents' => 0,
                    'net_cents' => 0,
                    'refund_cents' => 0,
                    'refunded_items' => 0,
                    'by_payment' => [],
                    'items' => [],
                ],
            ]);
        }

        // 4) ✅ Get payment breakdown (today only) - separate query
        $baseOrders = Order::query()->whereBetween('created_at', [$startAt, $endAt]);
        if ($userId) {
            $baseOrders->where('created_by', $userId);
        }
        $orderIdsSub = $baseOrders->select('id');

        $paymentsAgg = DB::table('payments')
            ->whereDate('created_at', $today)
            ->when($userId, function ($q) use ($userId) {
                return $q->whereIn('order_id', 
                    Order::query()->where('created_by', $userId)->select('id')
                );
            })
            ->select('method')
            ->selectRaw('SUM(amount_cents) as total_cents')
            ->groupBy('method')
            ->get();

        $byPayment = [];
        foreach ($paymentsAgg as $row) {
            $byPayment[$row->method] = (int) $row->total_cents;
        }

        // 5) ✅ Get items (optional - could be cached separately)
        $itemsAgg = DB::table('order_items')
            ->whereIn('order_id', $orderIdsSub)
            ->select('name')
            ->selectRaw('SUM(qty) as qty')
            ->selectRaw('SUM(price_cents * qty) as total_cents')
            ->groupBy('name')
            ->orderBy('name')
            ->limit(100) // ✅ Limit untuk performa
            ->get();

        $items = $itemsAgg->map(fn($it) => [
            'name' => $it->name,
            'qty' => (int) $it->qty,
            'total_cents' => (int) $it->total_cents,
        ])->values()->all();

        return response()->json([
            'ok' => true,
            'active' => [
                'id' => $shift->id,
                'start_at' => $startAt,
                'opening_cash_cents' => $shift->opening_cash_cents,
                'outlet_name' => $shift->outlet_name,
                'cashier_name' => $shift->cashier_name,
            ],
            'summary' => [
                'start_at' => $startAt,
                'end_at' => $endAt,
                'orders_count' => (int) $data->orders_count,
                'sold_items' => (int) $data->sold_items_today,
                'gross_cents' => (int) $data->gross_cents,
                'discount_cents' => (int) $data->discount_cents,
                'tax_cents' => (int) $data->tax_cents,
                'net_cents' => (int) $data->net_cents,
                'refund_cents' => (int) $data->refund_cents,
                'refunded_items' => 0,
                'by_payment' => $byPayment,
                'items' => $items,
            ],
        ]);
    }

    // ✅ Helper untuk build query params
    private function buildQueryParams($userId, $startAt, $endAt, $today)
    {
        $params = [];
        
        if ($userId) {
            $params[] = $userId; // sold_items_today subquery
        }
        
        $params[] = $startAt; // refund subquery start
        $params[] = $endAt;   // refund subquery end
        
        if ($userId) {
            $params[] = $userId; // refund subquery user filter
        }
        
        $params[] = $startAt; // main query start
        $params[] = $endAt;   // main query end
        
        if ($userId) {
            $params[] = $userId; // main query user filter
        }
        
        return $params;
    }

    public function show(Request $r, $id)
    {
        try {
            $u = $this->currentUser($r);
            $shift = Shift::findOrFail($id);

            if ($u && $shift->created_by && (int)$shift->created_by !== (int)$u->id) {
                return response()->json(['ok' => false, 'message' => 'Forbidden'], 403);
            }

            return response()->json(['ok' => true, 'shift' => $shift]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['ok' => false, 'message' => 'Shift not found'], 404);
        }
    }

    public function store(Request $r)
    {
        try {
            $data = $r->validate([
                'start_at' => 'required|date',
                'end_at' => 'required|date',
                'opening_cash_cents' => 'required|integer|min:0',
                'orders_count' => 'required|integer|min:0',
                'sold_items' => 'required|integer|min:0',
                'gross_cents' => 'required|integer|min:0',
                'discount_cents' => 'required|integer|min:0',
                'tax_cents' => 'required|integer|min:0',
                'net_cents' => 'required|integer|min:0',
                'by_payment' => 'nullable|array',
                'items' => 'nullable|array',
                'outlet_name' => 'nullable|string|max:255',
                'cashier_name' => 'nullable|string|max:255',
            ]);

            $u = $this->currentUser($r);

            $shift = Shift::create([
                'start_at' => $data['start_at'],
                'end_at' => $data['end_at'],
                'opening_cash_cents' => $data['opening_cash_cents'],
                'orders_count' => $data['orders_count'],
                'sold_items' => $data['sold_items'],
                'gross_cents' => $data['gross_cents'],
                'discount_cents' => $data['discount_cents'],
                'tax_cents' => $data['tax_cents'],
                'net_cents' => $data['net_cents'],
                'by_payment' => $data['by_payment'] ?? null,
                'items' => $data['items'] ?? null,
                'outlet_name' => $data['outlet_name'] ?? null,
                'cashier_name' => $data['cashier_name'] ?? null,
                'created_by' => $u?->id,
            ]);

            // ✅ Clear cache
            $this->clearShiftCache($u?->id);

            return response()->json([
                'ok' => true,
                'id' => $shift->id,
                'message' => 'Shift saved successfully',
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function start(Request $r)
    {
        try {
            // ✅ Check existing active shift first
            $existingShift = Shift::query()
                ->whereNull('end_at')
                ->whereNull('created_by')
                ->first();

            if ($existingShift) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Active shift already exists',
                    'shift_id' => $existingShift->id,
                ], 409);
            }

            $data = $r->validate([
                'start_at' => 'nullable|date',
                'opening_cash_cents' => 'required|integer|min:0',
                'outlet_name' => 'nullable|string|max:255',
                'cashier_name' => 'nullable|string|max:255',
            ]);

            $shift = Shift::create([
                'start_at' => $data['start_at'] ?? now(),
                'opening_cash_cents' => $data['opening_cash_cents'],
                'outlet_name' => $data['outlet_name'] ?? null,
                'cashier_name' => $data['cashier_name'] ?? null,
                'created_by' => null,
            ]);

            // ✅ Clear cache
            $this->clearShiftCache(null);

            return response()->json(['ok' => true, 'id' => $shift->id], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Shift start failed', [
                'msg' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'ok' => false,
                'message' => 'Server error',
            ], 500);
        }
    }

    public function close(Request $r, $id)
    {
        try {
            $data = $r->validate([
                'end_at' => 'nullable|date',
                'orders_count' => 'required|integer|min:0',
                'sold_items' => 'required|integer|min:0',
                'gross_cents' => 'required|integer|min:0',
                'discount_cents' => 'required|integer|min:0',
                'tax_cents' => 'required|integer|min:0',
                'net_cents' => 'required|integer|min:0',
                'by_payment' => 'nullable|array',
                'items' => 'nullable|array',
                'outlet_name' => 'nullable|string|max:255',
                'cashier_name' => 'nullable|string|max:255',
            ]);

            $shift = Shift::findOrFail($id);

            if ($shift->end_at !== null) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Shift already closed'
                ], 409);
            }

            $shift->update([
                'end_at' => $data['end_at'] ?? now(),
                'orders_count' => $data['orders_count'],
                'sold_items' => $data['sold_items'],
                'gross_cents' => $data['gross_cents'],
                'discount_cents' => $data['discount_cents'],
                'tax_cents' => $data['tax_cents'],
                'net_cents' => $data['net_cents'],
                'by_payment' => $data['by_payment'] ?? null,
                'items' => $data['items'] ?? null,
                'outlet_name' => $data['outlet_name'] ?? $shift->outlet_name,
                'cashier_name' => $data['cashier_name'] ?? $shift->cashier_name,
            ]);

            // ✅ Clear cache
            $this->clearShiftCache($shift->created_by);

            return response()->json(['ok' => true, 'id' => $shift->id]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['ok' => false, 'message' => 'Shift not found'], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Shift close failed', [
                'msg' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);
            return response()->json(['ok' => false, 'message' => 'Server error'], 500);
        }
    }

    public function destroy(Request $r, $id)
    {
        try {
            $u = $this->currentUser($r);
            $shift = Shift::findOrFail($id);

            if ($u && $shift->created_by && (int)$shift->created_by !== (int)$u->id) {
                return response()->json(['ok' => false, 'message' => 'Forbidden'], 403);
            }

            $shift->delete();

            // ✅ Clear cache
            $this->clearShiftCache($u?->id);

            return response()->json(['ok' => true, 'message' => 'Shift deleted']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['ok' => false, 'message' => 'Shift not found'], 404);
        }
    }

    // ✅ Helper: Clear shift cache
    private function clearShiftCache($userId)
    {
        $key = "active_shift_summary_" . ($userId ?? 'public');
        Cache::forget($key);
        Cache::tags(['shifts'])->flush();
    }
}