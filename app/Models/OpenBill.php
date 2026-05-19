<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpenBill extends Model
{
    protected $table = 'open_bills';

    protected $fillable = [
        'user_id',
        'client_id',
        'status',
        'subtotal_cents',
        'discount_cents',
        'tax_cents',
        'total_cents',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
