<?php

return [
    'cache' => [
        'master_ttl' => (int) env('POS_MASTER_CACHE_TTL', 300),
        'discount_ttl' => (int) env('POS_DISCOUNT_CACHE_TTL', 300),
        'shift_summary_ttl' => (int) env('POS_SHIFT_SUMMARY_TTL', 10),
    ],

    'limits' => [
        'orders_default' => 500,
        'orders_max' => 2000,
        'stock_moves_default' => 500,
        'stock_moves_max' => 5000,
        'open_bills_default' => 100,
        'open_bills_max' => 500,
    ],

    'fonnte' => [
        'token' => env('FONNTE_TOKEN'),
        'target' => env('SUPPLIER_WHATSAPP', env('ADMIN_WHATSAPP')),
        'url' => env('FONNTE_API_URL', 'https://api.fonnte.com/send'),
    ],
];
