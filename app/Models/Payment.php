<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    public $timestamps = false;
    protected $fillable = ['order_id','method','amount_cents','created_by','created_at'];
    protected $casts = ['created_at'=>'datetime'];
    public function order(){ return $this->belongsTo(Order::class); }
}
