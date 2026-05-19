<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\CachesSchemaColumns;
use App\Models\Menu;
use App\Services\MasterDataCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\BundleMenu;
use App\Models\BundleMenuItem;
use App\Models\BundleComponent;

class BundleMenuController extends BaseApiController
{
    use CachesSchemaColumns;
    public function index(Request $r)
    {
        $u = $this->currentUser($r);
        $userId = $u?->id;

        return Cache::remember(
            MasterDataCache::bundlesKey($userId),
            MasterDataCache::ttl(),
            fn () => $this->buildBundlesResponse($u)
        );
    }

    private function buildBundlesResponse($u)
    {
        $q = BundleMenu::query();
        if ($u) {
            $q->where('created_by', $u->id);
        }

        $bundles = $q->orderBy('sort')->orderBy('id')->get();
        $bundleCodes = $bundles->pluck('bundle_code')->all();

        if ($bundleCodes === []) {
            return [];
        }

        $bundleItemsQ = BundleMenuItem::whereIn('bundle_code', $bundleCodes);
        $bundleCompsQ = BundleComponent::whereIn('bundle_code', $bundleCodes);

        if ($u) {
            if ($this->tableHasColumn('bundle_menu_items', 'created_by')) {
                $bundleItemsQ->where('created_by', $u->id);
            }
            if ($this->tableHasColumn('bundle_components', 'created_by')) {
                $bundleCompsQ->where('created_by', $u->id);
            }
        }

        $items = $bundleItemsQ->get()->groupBy('bundle_code');
        $components = $bundleCompsQ->get()->groupBy('bundle_code');

        return $bundles->map(function($bundle) use ($items, $components) {
            return [
                'id' => $bundle->id,
                'code' => $bundle->bundle_code,
                'name' => $bundle->name,
                'price_rupiah' => $bundle->price_rupiah,
                'enabled' => $bundle->enabled,
                'sort' => $bundle->sort,
                'type' => $bundle->type ?? 'bundle',
                'bundle_items' => ($items[$bundle->bundle_code] ?? collect())->map(function($item) {
                    return [
                        'menu_code' => $item->menu_code,
                        'menu_name' => $item->menu_name,
                        'menu_type' => $item->menu_type,
                        'qty' => $item->qty,
                        'price_rupiah' => $item->price_rupiah,
                    ];
                })->values(),
                'components' => ($components[$bundle->bundle_code] ?? collect())->map(function($comp) {
                    return [
                        'product_sku' => $comp->product_sku,
                        'qty' => $comp->qty,
                    ];
                })->values(),
                'created_by' => $bundle->created_by,
                'created_by_id' => $bundle->created_by_id,
            ];
        })->values()->all();
    }

    public function store(Request $r)
    {
        $this->mergeNormalizedInput($r);

        $data = $r->validate([
            'code' => 'required|string|unique:bundle_menus,bundle_code',
            'name' => 'required|string',
            'price_rupiah' => 'required|integer|min:0',
            'enabled' => 'nullable|boolean',
            'sort' => 'nullable|integer',
            'type' => 'nullable|string|in:bundle,combo,set',
            'bundle_items' => 'required|array|min:1',
            'bundle_items.*.menu_code' => 'required|string|exists:menus,code',
            'bundle_items.*.qty' => 'required|integer|min:1',
            'components' => 'nullable|array',
            'components.*.product_sku' => 'required_with:components|string|exists:products,sku',
            'components.*.qty' => 'required_with:components|integer|min:1',
        ]);

        $u = $this->currentUser($r);

        return DB::transaction(function() use ($data, $u) {
            // Buat bundle menu
            $bundle = BundleMenu::create([
                'bundle_code' => $data['code'],
                'name' => $data['name'],
                'price_rupiah' => $data['price_rupiah'],
                'enabled' => $data['enabled'] ?? true,
                'sort' => $data['sort'] ?? 0,
                'type' => $data['type'] ?? 'bundle',
                'created_by' => $u?->username,
                'created_by_id' => $u?->id,
            ]);

            $menus = Menu::whereIn('code', array_column($data['bundle_items'], 'menu_code'))
                ->get()
                ->keyBy('code');

            $this->insertBundleItems($bundle->bundle_code, $data['bundle_items'], $menus, $u?->username);
            $this->insertBundleComponents($bundle->bundle_code, $data['components'] ?? [], $u?->username);

            MasterDataCache::forgetBundles($u?->id);

            return response()->json(['ok' => true, 'id' => $bundle->id, 'code' => $bundle->bundle_code]);
        });
    }

    public function update(Request $r, $code)
    {
        $data = $r->validate([
            'name' => 'sometimes|string',
            'price_rupiah' => 'sometimes|integer|min:0',
            'enabled' => 'sometimes|boolean',
            'sort' => 'sometimes|integer',
            'type' => 'sometimes|string|in:bundle,combo,set',
            'bundle_items' => 'sometimes|array|min:1',
            'components' => 'sometimes|array',
        ]);

        $u = $this->currentUser($r);

        return DB::transaction(function() use ($code, $data, $u) {
            $bundle = BundleMenu::where('bundle_code', $code)
                        ->when($u, fn($q) => $q->where('created_by', $u->id))
                        ->firstOrFail();

            $bundle->update($data);

            // Update bundle items jika dikirim
            if (array_key_exists('bundle_items', $data)) {
                BundleMenuItem::where('bundle_code', $code)
                    ->when($u && $this->tableHasColumn('bundle_menu_items', 'created_by'),
                        fn($q) => $q->where('created_by', $u->username))
                    ->delete();

                $menus = Menu::whereIn('code', array_column($data['bundle_items'], 'menu_code'))
                    ->get()
                    ->keyBy('code');

                $this->insertBundleItems($code, $data['bundle_items'], $menus, $u?->username);
            }

            if (array_key_exists('components', $data)) {
                BundleComponent::where('bundle_code', $code)
                    ->when($u && $this->tableHasColumn('bundle_components', 'created_by'),
                        fn($q) => $q->where('created_by', $u->username))
                    ->delete();

                $this->insertBundleComponents($code, $data['components'], $u?->username);
            }

            MasterDataCache::forgetBundles($u?->id);

            return ['ok' => true];
        });
    }

    public function destroy(Request $r, $code)
    {
        $u = $this->currentUser($r);

        return DB::transaction(function() use ($code, $u) {
            $bundle = BundleMenu::where('bundle_code', $code)
                        ->when($u, fn($q) => $q->where('created_by', $u->id))
                        ->firstOrFail();

            // Hapus anak-anak
            BundleMenuItem::where('bundle_code', $code)
                ->when($u && $this->tableHasColumn('bundle_menu_items', 'created_by'),
                    fn($q) => $q->where('created_by', $u->username))
                ->delete();

            BundleComponent::where('bundle_code', $code)
                ->when($u && $this->tableHasColumn('bundle_components', 'created_by'),
                    fn($q) => $q->where('created_by', $u->username))
                ->delete();

            $bundle->delete();

            MasterDataCache::forgetBundles($u?->id);

            return ['ok' => true];
        });
    }

    private function insertBundleItems(string $bundleCode, array $items, $menus, ?string $createdBy): void
    {
        $rows = [];
        foreach ($items as $item) {
            $menu = $menus[$item['menu_code']] ?? null;
            if (! $menu) {
                continue;
            }
            $rows[] = [
                'bundle_code' => $bundleCode,
                'menu_code'   => $item['menu_code'],
                'menu_name'   => $menu->name,
                'menu_type'   => $menu->type,
                'qty'         => $item['qty'],
                'price_rupiah' => $menu->price_rupiah,
                'created_by'  => $createdBy,
            ];
        }
        if ($rows !== []) {
            BundleMenuItem::insert($rows);
        }
    }

    private function insertBundleComponents(string $bundleCode, array $components, ?string $createdBy): void
    {
        if ($components === []) {
            return;
        }
        $rows = [];
        foreach ($components as $component) {
            $rows[] = [
                'bundle_code' => $bundleCode,
                'product_sku' => $component['product_sku'],
                'qty'         => $component['qty'],
                'created_by'  => $createdBy,
            ];
        }
        BundleComponent::insert($rows);
    }
}