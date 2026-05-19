<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
  protected $fillable = ['order_id','total_cents','reason','payload'];
  protected $casts = ['payload' => 'array'];

  public function items() { return $this->hasMany(RefundItem::class); }
  public function order() { return $this->belongsTo(Order::class); }
}
