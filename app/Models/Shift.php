<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $fillable = [
        'start_at',
        'end_at',
        'opening_cash_cents',
        'orders_count',
        'sold_items',
        'gross_cents',
        'discount_cents',
        'tax_cents',
        'net_cents',
        'by_payment', // JSON
        'items',      // JSON
        'created_by',
        'outlet_name',
        'cashier_name',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'by_payment' => 'array',
        'items' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}