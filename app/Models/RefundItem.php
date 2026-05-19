<?php

// namespace App\Models;

// use Illuminate\Database\Eloquent\Model;

// class RefundItem extends Model
// {
//   protected $fillable = [
//     'refund_id','order_id','order_item_id','sku','menu_code','qty','unit_price_rupiah'
//   ];

//   public function refund()    { return $this->belongsTo(Refund::class); }
//   public function order()     { return $this->belongsTo(Order::class); }
//   public function orderItem() { return $this->belongsTo(OrderItem::class); }
// }



namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundItem extends Model
{
    protected $fillable = [
        'refund_id',
        'order_id',
        'order_item_id',
        'sku',
        'menu_code',
        'name', // TAMBAHAN: nama item
        'qty',
        'unit_price_rupiah'
    ];

    public function refund()
    {
        return $this->belongsTo(Refund::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    // Hitung total per item
    public function getTotalRupiahAttribute()
    {
        return $this->qty * $this->unit_price_rupiah;
    }
}