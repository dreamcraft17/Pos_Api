<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMove extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sku',
        'delta',
        'stock_after', // << baru
        'reason',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
