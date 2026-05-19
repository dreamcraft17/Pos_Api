<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\CachesSchemaColumns;
use Illuminate\Http\Request;
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

        $q = Menu::query();
        if ($u) {
            $q->where('created_by', $u->id);
        }

        $menus = $q->orderBy('sort')->orderBy('id')->get();
        $codes = $menus->pluck('code')->all();

        if ($codes === []) {
            return collect();
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

        return $menus->map(function($m) use ($items, $variants) {
            return [
                'id'          => $m->id,
                'code'        => $m->code,
                'name'        => $m->name,
                'price_cents' => $m->price_cents,
                'image_url'   => $m->image_url,
                'enabled'     => $m->enabled,
                'sort'        => $m->sort,
                'type'        => $m->type,
                'components'  => ($items[$m->code] ?? collect())->values(),
                'variants'    => ($variants[$m->code] ?? collect())->values(),
            ];
        });
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'code'                       => 'required|string',
            'name'                       => 'required|string',
            'price_cents'                => 'required|integer|min:0',
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
            'variants.*.price_cents'     => 'required_with:variants|integer|min:0',
        ]);

        $u = $this->currentUser($r);

        return DB::transaction(function() use ($data, $u) {
            // Penting: SELALU membuat baris baru (bukan updateOrCreate by code)
            $menu = Menu::create([
                'code'        => $data['code'],
                'name'        => $data['name'],
                'price_cents' => $data['price_cents'],
                'image_url'   => $data['image_url'] ?? null,
                'enabled'     => $data['enabled'] ?? true,
                'sort'        => $data['sort'] ?? 0,
                'created_by'  => $u?->id,
                'type'        => $data['type'] ?? null,
            ]);

            // === Pakai menu_code (BUKAN menu_id) ===
            if (!empty($data['components'])) {
                foreach ($data['components'] as $c) {
                    MenuItem::create([
                        'menu_code'   => $menu->code,
                        'product_sku' => $c['product_sku'],
                        'qty'         => $c['qty'],
                        'created_by'  => $u?->id,
                    ]);
                }
            }

            if (!empty($data['variants'])) {
                foreach ($data['variants'] as $v) {
                    MenuVariant::create([
                        'menu_code'   => $menu->code,
                        'kind'        => $v['kind'],
                        'category'    => $v['category'] ?? null,
                        'size'        => $v['size'] ?? null,
                        'price_cents' => $v['price_cents'],
                        'created_by'  => $u?->id,
                    ]);
                }
            }

            return ['ok' => true, 'id' => $menu->id];
        });
    }

    // Ganti ke update-by-id agar tidak bentrok jika code duplikat
    // public function update(Request $r, $id)
    // {
    //     $data = $r->validate([
    //         'name'         => 'sometimes|string',
    //         'price_cents'  => 'sometimes|integer|min:0',
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
    //                     'price_cents' => $v['price_cents'],
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
        'price_cents'  => 'sometimes|integer|min:0',
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

            foreach ($data['components'] ?? [] as $c) {
                MenuItem::create([
                    'menu_code'   => $menu->code,
                    'product_sku' => $c['product_sku'],
                    'qty'         => $c['qty'],
                    'created_by'  => $u?->id,
                ]);
            }
        }

        if (array_key_exists('variants', $data)) {
            MenuVariant::where('menu_code', $menu->code)
                ->when($u && $this->tableHasColumn('menu_variants', 'created_by'),
                    fn($q) => $q->where('created_by', $u->id))
                ->delete();

            foreach ($data['variants'] ?? [] as $v) {
                MenuVariant::create([
                    'menu_code'   => $menu->code,
                    'kind'        => $v['kind'],
                    'category'    => $v['category'] ?? null,
                    'size'        => $v['size'] ?? null,
                    'price_cents' => $v['price_cents'],
                    'created_by'  => $u?->id,
                ]);
            }
        }

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

        return ['ok' => true];
    });
    }
}
