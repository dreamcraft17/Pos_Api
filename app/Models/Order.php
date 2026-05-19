<?php

// namespace App\Models;

// use Illuminate\Database\Eloquent\Model;

// // class Order extends Model
// // {
// //     public $timestamps = false; // only created_at
// //     protected $fillable = ['subtotal_cents','discount_cents','tax_cents','total_cents','payload','created_by','created_at'];
// //     protected $casts = ['payload'=>'array','created_at'=>'datetime'];
// //     public function items(){ return $this->hasMany(OrderItem::class); }
// //     public function payments(){ return $this->hasMany(Payment::class); }
// // }

// // class Order extends Model
// // {
// //     public $timestamps = false;
// //     protected $fillable = [
// //         'subtotal_cents','discount_cents','tax_cents',
// //         'total_cents','payload','created_by','staff_name','created_at'
// //     ];
// //     protected $casts = ['payload'=>'array','created_at'=>'datetime'];

// //     public function items(){ return $this->hasMany(OrderItem::class); }
// //     public function payments(){ return $this->hasMany(Payment::class); }
// // }

// // class Order extends Model
// // {
// //     public $timestamps = false;
// //     protected $fillable = [
// //         'subtotal_cents','discount_cents','tax_cents',
// //         'total_cents','payload','created_by','staff_name',
// //        'order_type',
// //         'created_at'
// //     ];

// //     protected $casts = ['payload'=>'array','created_at'=>'datetime'];

// //     public function items(){ return $this->hasMany(OrderItem::class); }
// //     public function payments(){ return $this->hasMany(Payment::class); }
// // }

// class Order extends Model
// {
//     public $timestamps = false;
//     protected $fillable = [
//         'subtotal_cents',
//         'discount_cents',
//         'service_cents',   // NEW
//         'tax_cents',
//         'total_cents',
//         'payload',
//         'created_by',
//         'staff_name',
//         'order_type',
//         'created_at'
//     ];

//     protected $casts = [
//         'payload'    => 'array',
//         'created_at' => 'datetime',
//     ];

//     public function items()
//     {
//         return $this->hasMany(OrderItem::class);
//     }

//     public function payments()
//     {
//         return $this->hasMany(Payment::class);
//     }
// }




namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'subtotal_cents',
        'discount_cents',
        'service_cents',
        'tax_cents',
        'total_cents',
        'refund_total_cents', // TAMBAHAN: total yang sudah direfund
        'payload',
        'created_by',
        'staff_name',
        'order_type',
        'created_at'
    ];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    // Hitung total yang sudah dibayar (berdasarkan payments)
    public function getPaidAmountCentsAttribute()
    {
        return $this->payments()->sum('amount_cents');
    }

    // Hitung sisa yang harus dibayar setelah refund
    public function getRemainingBalanceCentsAttribute()
    {
        return $this->paid_amount_cents - $this->refund_total_cents;
    }
}