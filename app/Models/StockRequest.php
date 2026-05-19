<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockRequest extends Model
{
// protected $fillable = [
//   'requested_by','notes','sent_at','channel','status',
//   'wa_target','wa_response_code','wa_response_body',
//   'request_status', // ← WAJIB
// ];


//     protected $casts = [
//         'sent_at' => 'datetime',
//     ];

// App/Models/StockRequest.php
protected $fillable = [
  'requested_by','notes','sent_at','channel','status',
  'wa_target','wa_response_code','wa_response_body',
  'request_status',        // existing
  'branch',                // NEW
  'approval_state',        // NEW: pending|approved|rejected
  'approved_by',           // NEW
  'approved_at',           // NEW
];

protected $casts = [
  'sent_at'     => 'datetime',
  'approved_at' => 'datetime', // NEW
];


    public function items() {
        return $this->hasMany(StockRequestItem::class);
    }
}
