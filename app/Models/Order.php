<?php

// namespace App\Models;

// use Illuminate\Database\Eloquent\Model;

// // class Order extends Model
// // {
// //     public $timestamps = false; // only created_at
// //     protected $fillable = ['subtotal_rupiah','discount_rupiah','tax_rupiah','total_rupiah','payload','created_by','created_at'];
// //     protected $casts = ['payload'=>'array','created_at'=>'datetime'];
// //     public function items(){ return $this->hasMany(OrderItem::class); }
// //     public function payments(){ return $this->hasMany(Payment::class); }
// // }

// // class Order extends Model
// // {
// //     public $timestamps = false;
// //     protected $fillable = [
// //         'subtotal_rupiah','discount_rupiah','tax_rupiah',
// //         'total_rupiah','payload','created_by','staff_name','created_at'
// //     ];
// //     protected $casts = ['payload'=>'array','created_at'=>'datetime'];

// //     public function items(){ return $this->hasMany(OrderItem::class); }
// //     public function payments(){ return $this->hasMany(Payment::class); }
// // }

// // class Order extends Model
// // {
// //     public $timestamps = false;
// //     protected $fillable = [
// //         'subtotal_rupiah','discount_rupiah','tax_rupiah',
// //         'total_rupiah','payload','created_by','staff_name',
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
//         'subtotal_rupiah',
//         'discount_rupiah',
//         'service_rupiah',   // NEW
//         'tax_rupiah',
//         'total_rupiah',
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
        'subtotal_rupiah',
        'discount_rupiah',
        'service_rupiah',
        'tax_rupiah',
        'total_rupiah',
        'refund_total_rupiah', // TAMBAHAN: total yang sudah direfund
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

    public function getPaidAmountRupiahAttribute(): int
    {
        if ($this->relationLoaded('payments')) {
            return (int) $this->payments->sum('amount_rupiah');
        }

        return (int) $this->payments()->sum('amount_rupiah');
    }

    public function getRemainingBalanceRupiahAttribute(): int
    {
        return $this->paid_amount_rupiah - (int) ($this->refund_total_rupiah ?? 0);
    }
}