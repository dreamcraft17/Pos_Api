<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    public $timestamps = false;
    protected $fillable = ['order_id','sku','menu_code','bundle_code','qty','price_rupiah','name','temp','size','created_by'];
    public function order(){ return $this->belongsTo(Order::class); }
}
