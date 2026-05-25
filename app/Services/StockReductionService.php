<?php

namespace App\Services;

use App\Models\BundleComponent;
use App\Models\BundleMenuItem;
use App\Models\MenuItem;
use App\Models\Product;
use Illuminate\Support\Collection;

class StockReductionService
{
    /**
     * @return array{
     *   products: Collection<string, Product>,
     *   menu_components: Collection,
     *   bundle_menu_items: Collection,
     *   bundle_components: Collection
     * }
     */
    public function preload(array $items, ?Collection $orderItemsById = null): array
    {
        $skus = [];
        $menuCodes = [];
        $bundleCodes = [];

        foreach ($items as $it) {
            if (! empty($it['sku'])) {
                $skus[] = $it['sku'];
            } elseif (! empty($it['menu_code'])) {
                $menuCodes[] = $it['menu_code'];
            } elseif (! empty($it['bundle_code'])) {
                $bundleCodes[] = $it['bundle_code'];
            } elseif (! empty($it['order_item_id']) && $orderItemsById) {
                $oi = $orderItemsById[$it['order_item_id']] ?? null;
                if ($oi?->sku) {
                    $skus[] = $oi->sku;
                } elseif ($oi?->menu_code) {
                    $menuCodes[] = $oi->menu_code;
                } elseif ($oi?->bundle_code ?? null) {
                    $bundleCodes[] = $oi->bundle_code;
                }
            }
        }

        $bundleCodes = array_unique($bundleCodes);
        $bundleMenuItems = collect();
        $bundleComponents = collect();

        if ($bundleCodes !== [] && $this->bundleTablesExist()) {
            $bundleMenuItems = BundleMenuItem::whereIn('bundle_code', $bundleCodes)->get()->groupBy('bundle_code');
            $bundleComponents = BundleComponent::whereIn('bundle_code', $bundleCodes)->get()->groupBy('bundle_code');

            foreach ($bundleMenuItems as $rows) {
                foreach ($rows as $row) {
                    $menuCodes[] = $row->menu_code;
                }
            }
            foreach ($bundleComponents as $rows) {
                foreach ($rows as $row) {
                    $skus[] = $row->product_sku;
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

        return [
            'products' => $products,
            'menu_components' => $menuComponents,
            'bundle_menu_items' => $bundleMenuItems,
            'bundle_components' => $bundleComponents,
        ];
    }

    public function reduce(
        array $item,
        int $qtyMultiplier,
        array $context,
        array &$stockMoves,
        ?int $userId,
        string $reason = 'order'
    ): void {
        if (! empty($item['sku'])) {
            $this->reduceSku($context['products'], $item['sku'], $qtyMultiplier, $stockMoves, $userId, "{$reason} direct item");

            return;
        }

        if (! empty($item['menu_code'])) {
            $this->reduceMenu($context, $item['menu_code'], $qtyMultiplier, $stockMoves, $userId, "{$reason} menu component");

            return;
        }

        if (! empty($item['bundle_code']) && $this->bundleTablesExist()) {
            $this->reduceBundle($context, $item['bundle_code'], $qtyMultiplier, $stockMoves, $userId, $reason);
        }
    }

    public function restock(
        array $item,
        int $qtyMultiplier,
        array $context,
        array &$stockMoves,
        ?Collection $orderItemsById = null
    ): void {
        if (! empty($item['sku'])) {
            $this->increaseSku($context['products'], $item['sku'], $qtyMultiplier, $stockMoves);

            return;
        }

        if (! empty($item['menu_code'])) {
            $this->restockMenu($context, $item['menu_code'], $qtyMultiplier, $stockMoves);

            return;
        }

        if (! empty($item['bundle_code']) && $this->bundleTablesExist()) {
            $this->restockBundle($context, $item['bundle_code'], $qtyMultiplier, $stockMoves);

            return;
        }

        if (! empty($item['order_item_id']) && $orderItemsById) {
            $oi = $orderItemsById[$item['order_item_id']] ?? null;
            if (! $oi) {
                return;
            }
            $nested = [
                'sku' => $oi->sku,
                'menu_code' => $oi->menu_code,
                'bundle_code' => $oi->bundle_code ?? null,
            ];
            $this->restock($nested, $qtyMultiplier, $context, $stockMoves);
        }
    }

    private function reduceBundle(array $context, string $bundleCode, int $qtyMultiplier, array &$stockMoves, ?int $userId, string $reason): void
    {
        foreach ($context['bundle_menu_items'][$bundleCode] ?? [] as $bmi) {
            $this->reduceMenu($context, $bmi->menu_code, $qtyMultiplier * (int) $bmi->qty, $stockMoves, $userId, "{$reason} bundle menu");
        }
        foreach ($context['bundle_components'][$bundleCode] ?? [] as $bc) {
            $this->reduceSku($context['products'], $bc->product_sku, $qtyMultiplier * (int) $bc->qty, $stockMoves, $userId, "{$reason} bundle product");
        }
    }

    private function restockBundle(array $context, string $bundleCode, int $qtyMultiplier, array &$stockMoves): void
    {
        foreach ($context['bundle_menu_items'][$bundleCode] ?? [] as $bmi) {
            $this->restockMenu($context, $bmi->menu_code, $qtyMultiplier * (int) $bmi->qty, $stockMoves);
        }
        foreach ($context['bundle_components'][$bundleCode] ?? [] as $bc) {
            $this->increaseSku($context['products'], $bc->product_sku, $qtyMultiplier * (int) $bc->qty, $stockMoves);
        }
    }

    private function reduceMenu(array $context, string $menuCode, int $qtyMultiplier, array &$stockMoves, ?int $userId, string $reason): void
    {
        foreach ($context['menu_components'][$menuCode] ?? [] as $c) {
            $this->reduceSku(
                $context['products'],
                $c->product_sku,
                $c->qty * $qtyMultiplier,
                $stockMoves,
                $userId,
                $reason
            );
        }
    }

    private function restockMenu(array $context, string $menuCode, int $qtyMultiplier, array &$stockMoves): void
    {
        foreach ($context['menu_components'][$menuCode] ?? [] as $c) {
            $this->increaseSku($context['products'], $c->product_sku, $c->qty * $qtyMultiplier, $stockMoves);
        }
    }

    private function reduceSku(Collection $products, string $sku, int $qty, array &$stockMoves, ?int $userId, string $reason): void
    {
        if ($qty <= 0) {
            return;
        }
        $p = $products[$sku] ?? null;
        if (! $p) {
            throw new \InvalidArgumentException("Product SKU not found: {$sku}");
        }
        $p->decrement('stock', $qty);
        $stockMoves[] = [
            'sku' => $p->sku,
            'delta' => -$qty,
            'reason' => $reason,
            'created_by' => $userId,
        ];
    }

    private function increaseSku(Collection $products, string $sku, int $qty, array &$stockMoves): void
    {
        if ($qty <= 0) {
            return;
        }
        $p = $products[$sku] ?? null;
        if (! $p) {
            return;
        }
        $p->increment('stock', $qty);
        $stockMoves[] = [
            'sku' => $p->sku,
            'delta' => $qty,
            'reason' => 'refund',
        ];
    }

    private function bundleTablesExist(): bool
    {
        static $exists = null;
        if ($exists === null) {
            $exists = \Illuminate\Support\Facades\Schema::hasTable('bundle_menus')
                && \Illuminate\Support\Facades\Schema::hasTable('bundle_menu_items');
        }

        return $exists;
    }
}
