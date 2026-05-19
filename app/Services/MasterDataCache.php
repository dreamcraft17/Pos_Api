<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class MasterDataCache
{
    public static function ttl(): int
    {
        return config('pos.cache.master_ttl', 300);
    }

    public static function forgetProducts(): void
    {
        Cache::increment('pos:products:version');
    }

    public static function forgetMenus(?int $userId = null): void
    {
        if ($userId !== null) {
            Cache::forget(self::menusKey($userId));

            return;
        }
        Cache::increment('pos:menus:version');
    }

    public static function forgetBundles(?int $userId = null): void
    {
        if ($userId !== null) {
            Cache::forget(self::bundlesKey($userId));

            return;
        }
        Cache::increment('pos:bundles:version');
    }

    public static function forgetDiscounts(): void
    {
        Cache::forget('pos:discounts:enabled');
    }

    public static function menusKey(?int $userId): string
    {
        $version = Cache::get('pos:menus:version', 1);

        return 'pos:menus:v'.$version.':u'.($userId ?? 'all');
    }

    public static function bundlesKey(?int $userId): string
    {
        $version = Cache::get('pos:bundles:version', 1);

        return 'pos:bundles:v'.$version.':u'.($userId ?? 'all');
    }

    public static function productsKey(?string $updatedSince): string
    {
        $version = Cache::get('pos:products:version', 1);

        return 'pos:products:v'.$version.':'.($updatedSince ?? 'all');
    }
}
