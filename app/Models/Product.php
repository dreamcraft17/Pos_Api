<?php

// namespace App\Models;

// use Illuminate\Database\Eloquent\Model;

// class Product extends Model
// {
//     public $timestamps = false;
//     // protected $fillable = ['sku','name','price_cents','stock','is_deleted','created_by','updated_at'];
//     protected $fillable = ['sku','name','price_cents','stock','category','is_deleted','created_by','updated_at'];

//     protected $casts = ['updated_at'=>'datetime'];
// }


// namespace App\Models;

// use Illuminate\Database\Eloquent\Model;

// class Product extends Model
// {
//     public $timestamps = false;

//     protected $fillable = [
//         'sku',
//         'name',
//         'price_cents',
//         'stock',
//         'category',
//         'unit',      // << baru
//         'min_qty',   // << baru
//         'is_deleted',
//         'created_by',
//         'updated_at',
//         'station', 
//         'mandarin',
//         'brand',
//     ];

//     protected $casts = [
//         'updated_at' => 'datetime',
//     ];
// }


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sku',
        'name',
        'price_cents',
        'stock',
        'category',
        'unit',
        'min_qty',
        'is_deleted',
        'created_by',
        'updated_at',
        'station',
        'mandarin',
        'brand',
    ];

    protected $casts = [
        'updated_at' => 'datetime',
    ];
}