<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\CachesSchemaColumns;
use App\Services\MasterDataCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuVariant;

class MenuController extends BaseApiController
{
    use CachesSchemaColumns;

    public function index(Request $r)
    {
        $u = $this->currentUser($r);
        $userId = $u?->id;

        return Cache::remember(
            MasterDataCache::menusKey($userId),
            MasterDataCache::ttl(),
            fn () => $this->buildMenusResponse($u)
        );
    }

    private function buildMenusResponse($u)
    {
        $q = Menu::query();
        if ($u) {
            $q->where('created_by', $u->id);
        }

        $menus = $q->orderBy('sort')->orderBy('id')->get();
        $codes = $menus->pluck('code')->all();

        if ($codes === []) {
            return [];
        }

        $itemsQ = MenuItem::whereIn('menu_code', $codes);
        $varsQ  = MenuVariant::whereIn('menu_code', $codes);

        if ($u) {
            if ($this->tableHasColumn('menu_items', 'created_by')) {
                $itemsQ->where('created_by', $u->id);
            }
            if ($this->tableHasColumn('menu_variants', 'created_by')) {
                $varsQ->where('created_by', $u->id);
            }
        }

        $items    = $itemsQ->get()->groupBy('menu_code');
        $variants = $varsQ->get()->groupBy('menu_code');

        return $menus->map(function ($m) use ($items, $variants) {
            return [
                'id'          => $m->id,
                'code'        => $m->code,
                'name'        => $m->name,
                'price_rupiah' => $m->price_rupiah,
                'image_url'   => $m->image_url,
                'enabled'     => $m->enabled,
                'sort'        => $m->sort,
                'type'        => $m->type,
                'components'  => ($items[$m->code] ?? collect())->values(),
                'variants'    => ($variants[$m->code] ?? collect())->values(),
            ];
        })->values()->all();
    }

    public function store(Request $r)
    {
        $this->mergeNormalizedInput($r);

        $data = $r->validate([
            'code'                       => 'required|string',
            'name'                       => 'required|string',
            'price_rupiah'                => 'required|integer|min:0',
            'image_url'                  => 'nullable|string',
            'enabled'                    => 'nullable|boolean',
            'sort'                       => 'nullable|integer',
            'type'                       => 'nullable|string',
            'components'                 => 'nullable|array',
            'components.*.product_sku'   => 'required_with:components|string',
            'components.*.qty'           => 'required_with:components|integer|min:1',
            'variants'                   => 'nullable|array',
            'variants.*.kind'            => 'required_with:variants|in:drink,food',
            'variants.*.category'        => 'nullable|in:hot,ice',
            'variants.*.size'            => 'nullable|in:S,M,L',
            'variants.*.price_rupiah'     => 'required_with:variants|integer|min:0',
        ]);

        $u = $this->currentUser($r);

        return DB::transaction(function () use ($data, $u) {
            $menu = Menu::updateOrCreate(
                ['code' => $data['code'], 'created_by' => $u?->id],
                [
                    'name'         => $data['name'],
                    'price_rupiah' => $data['price_rupiah'],
                    'image_url'    => $data['image_url'] ?? null,
                    'enabled'      => $data['enabled'] ?? true,
                    'sort'         => $data['sort'] ?? 0,
                    'type'         => $data['type'] ?? null,
                ]
            );

            if ($this->tableHasColumn('menu_items', 'created_by')) {
                \App\Models\MenuItem::where('menu_code', $menu->code)
                    ->where('created_by', $u?->id)
                    ->delete();
            } else {
                \App\Models\MenuItem::where('menu_code', $menu->code)->delete();
            }

            if ($this->tableHasColumn('menu_variants', 'created_by')) {
                \App\Models\MenuVariant::where('menu_code', $menu->code)
                    ->where('created_by', $u?->id)
                    ->delete();
            } else {
                \App\Models\MenuVariant::where('menu_code', $menu->code)->delete();
            }

            $this->insertMenuItems($menu->code, $data['components'] ?? [], $u?->id);
            $this->insertMenuVariants($menu->code, $data['variants'] ?? [], $u?->id);

            MasterDataCache::forgetMenus($u?->id);

            return ['ok' => true, 'id' => $menu->id];
        });
    }

    // Ganti ke update-by-id agar tidak bentrok jika code duplikat
    // public function update(Request $r, $id)
    // {
    //     $data = $r->validate([
    //         'name'         => 'sometimes|string',
    //         'price_rupiah'  => 'sometimes|integer|min:0',
    //         'image_url'    => 'sometimes|nullable|string',
    //         'enabled'      => 'sometimes|boolean',
    //         'sort'         => 'sometimes|integer',
    //         'type'         => 'sometimes|string',
    //         'components'   => 'sometimes|array',
    //         'variants'     => 'sometimes|array',
    //     ]);

    //     $u = $this->currentUser($r);

    //     return DB::transaction(function() use ($id, $data, $u) {
    //         $menu = Menu::where('id', $id)
    //                     ->when($u, fn($q) => $q->where('created_by', $u->id))
    //                     ->firstOrFail();

    //         $menu->update($data);

    //         // Jika frontend mengirim full list: replace
    //         if (array_key_exists('components', $data)) {
    //             MenuItem::where('menu_code', $menu->code)
    //                 ->when($u && Schema()->hasColumn('menu_items','created_by'),
    //                     fn($q) => $q->where('created_by', $u->id))
    //                 ->delete();

    //             foreach ($data['components'] ?? [] as $c) {
    //                 MenuItem::create([
    //                     'menu_code'   => $menu->code,
    //                     'product_sku' => $c['product_sku'],
    //                     'qty'         => $c['qty'],
    //                     'created_by'  => $u?->id,
    //                 ]);
    //             }
    //         }

    //         if (array_key_exists('variants', $data)) {
    //             MenuVariant::where('menu_code', $menu->code)
    //                 ->when($u && Schema()->hasColumn('menu_variants','created_by'),
    //                     fn($q) => $q->where('created_by', $u->id))
    //                 ->delete();

    //             foreach ($data['variants'] ?? [] as $v) {
    //                 MenuVariant::create([
    //                     'menu_code'   => $menu->code,
    //                     'kind'        => $v['kind'],
    //                     'category'    => $v['category'] ?? null,
    //                     'size'        => $v['size'] ?? null,
    //                     'price_rupiah' => $v['price_rupiah'],
    //                     'created_by'  => $u?->id,
    //                 ]);
    //             }
    //         }

    //         return ['ok' => true];
    //     });
    // }

    public function update(Request $r, $code)
{
    $data = $r->validate([
        'name'         => 'sometimes|string',
        'price_rupiah'  => 'sometimes|integer|min:0',
        'image_url'    => 'sometimes|nullable|string',
        'enabled'      => 'sometimes|boolean',
        'sort'         => 'sometimes|integer',
        'type'         => 'sometimes|string',
        'components'   => 'sometimes|array',
        'variants'     => 'sometimes|array',
    ]);

    $u = $this->currentUser($r);

    return DB::transaction(function() use ($code, $data, $u) {
        $menu = Menu::where('code', $code)
                    ->when($u, fn($q) => $q->where('created_by', $u->id))
                    ->firstOrFail();

        $menu->update($data);

        if (array_key_exists('components', $data)) {
            MenuItem::where('menu_code', $menu->code)
                ->when($u && $this->tableHasColumn('menu_items', 'created_by'),
                    fn($q) => $q->where('created_by', $u->id))
                ->delete();

            $this->insertMenuItems($menu->code, $data['components'] ?? [], $u?->id);
        }

        if (array_key_exists('variants', $data)) {
            MenuVariant::where('menu_code', $menu->code)
                ->when($u && $this->tableHasColumn('menu_variants', 'created_by'),
                    fn($q) => $q->where('created_by', $u->id))
                ->delete();

            $this->insertMenuVariants($menu->code, $data['variants'] ?? [], $u?->id);
        }

        MasterDataCache::forgetMenus($u?->id);

        return ['ok' => true];
    });
}


    // public function destroy(Request $r, $id)
    // {
    //     $u = $this->currentUser($r);

    //     return DB::transaction(function() use ($id, $u) {
    //         $menu = Menu::where('id', $id)
    //                     ->when($u, fn($q) => $q->where('created_by', $u->id))
    //                     ->firstOrFail();

    //         // Hapus anak berdasarkan menu_code
    //         MenuItem::where('menu_code', $menu->code)
    //             ->when($u && Schema()->hasColumn('menu_items','created_by'),
    //                 fn($q) => $q->where('created_by', $u->id))
    //             ->delete();

    //         MenuVariant::where('menu_code', $menu->code)
    //             ->when($u && Schema()->hasColumn('menu_variants','created_by'),
    //                 fn($q) => $q->where('created_by', $u->id))
    //             ->delete();

    //         $menu->delete();

    //         return ['ok' => true];
    //     });
    // }

    public function destroy(Request $r, $code)
{
    $u = $this->currentUser($r);

    return DB::transaction(function() use ($code, $u) {
        $menu = Menu::where('code', $code)
                    ->when($u, fn($q) => $q->where('created_by', $u->id))
                    ->firstOrFail();

        MenuItem::where('menu_code', $menu->code)
            ->when($u && $this->tableHasColumn('menu_items', 'created_by'),
                fn($q) => $q->where('created_by', $u->id))
            ->delete();

        MenuVariant::where('menu_code', $menu->code)
            ->when($u && $this->tableHasColumn('menu_variants', 'created_by'),
                fn($q) => $q->where('created_by', $u->id))
            ->delete();

        $menu->delete();

        MasterDataCache::forgetMenus($u?->id);

        return ['ok' => true];
    });
    }

    private function insertMenuItems(string $menuCode, array $components, ?int $userId): void
    {
        if ($components === []) {
            return;
        }
        $rows = [];
        foreach ($components as $c) {
            $rows[] = [
                'menu_code'   => $menuCode,
                'product_sku' => $c['product_sku'],
                'qty'         => $c['qty'],
                'created_by'  => $userId,
            ];
        }
        MenuItem::insert($rows);
    }

    private function insertMenuVariants(string $menuCode, array $variants, ?int $userId): void
    {
        if ($variants === []) {
            return;
        }
        $rows = [];
        foreach ($variants as $v) {
            $rows[] = [
                'menu_code'   => $menuCode,
                'kind'        => $v['kind'],
                'category'    => $v['category'] ?? null,
                'size'        => $v['size'] ?? null,
                'price_rupiah' => $v['price_rupiah'],
                'created_by'  => $userId,
            ];
        }
        MenuVariant::insert($rows);
    }
}
