<?php

namespace App\Services;

use App\Models\Discount;
use Illuminate\Support\Facades\Cache;

class DiscountResolver
{
    /** @return array{0: int, 1: ?array} */
    public function resolve(?array $d, int $subtotal): array
    {
        if (! $d) {
            return [0, null];
        }

        $payload = $d;

        if (! empty($d['code'])) {
            $found = $this->findByCode($d['code']);
            if ($found && $found->enabled) {
                $payload = [
                    'code' => $found->code,
                    'kind' => $found->kind,
                    'value' => $found->value,
                ];

                return [$this->calculate($found->kind, (float) $found->value, $subtotal), $payload];
            }
        }

        if (! empty($d['kind']) && isset($d['value'])) {
            return [$this->calculate($d['kind'], (float) $d['value'], $subtotal), $payload];
        }

        return [0, null];
    }

    private function findByCode(string $code): ?Discount
    {
        $map = Cache::remember(
            'pos:discounts:enabled',
            config('pos.cache.discount_ttl', 300),
            fn () => Discount::query()
                ->where('enabled', true)
                ->get()
                ->keyBy('code')
        );

        return $map[$code] ?? null;
    }

    private function calculate(string $kind, float $value, int $subtotal): int
    {
        if ($kind === 'percent') {
            $off = (int) round($subtotal * $value / 100.0);

            return min($subtotal, $off);
        }

        return min($subtotal, (int) round($value));
    }
}
