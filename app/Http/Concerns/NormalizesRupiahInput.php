<?php

namespace App\Http\Concerns;

trait NormalizesRupiahInput
{
    /** @var array<string, string> */
    private static array $rupiahKeyAliases = [
        'price_rupiah' => 'price_rupiah',
        'unit_price_rupiah' => 'unit_price_rupiah',
        'amount_rupiah' => 'amount_rupiah',
        'subtotal_rupiah' => 'subtotal_rupiah',
        'discount_rupiah' => 'discount_rupiah',
        'service_rupiah' => 'service_rupiah',
        'tax_rupiah' => 'tax_rupiah',
        'total_rupiah' => 'total_rupiah',
        'opening_cash_rupiah' => 'opening_cash_rupiah',
        'gross_rupiah' => 'gross_rupiah',
        'net_rupiah' => 'net_rupiah',
    ];

    protected function normalizeRupiahKeys(mixed $data): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        $out = [];
        foreach ($data as $key => $value) {
            $newKey = self::$rupiahKeyAliases[$key] ?? $key;
            $out[$newKey] = $this->normalizeRupiahKeys($value);
        }

        return $out;
    }

    protected function mergeNormalizedInput(\Illuminate\Http\Request $request): void
    {
        $request->merge($this->normalizeRupiahKeys($request->all()));
    }
}
