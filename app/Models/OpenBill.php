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
        'subtotal_rupiah',
        'discount_rupiah',
        'tax_rupiah',
        'total_rupiah',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
