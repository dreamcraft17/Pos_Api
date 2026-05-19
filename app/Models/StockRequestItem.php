<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// class StockRequestItem extends Model
// {
// protected $fillable = [
//   'stock_request_id','name','request_qty','unit','note',
//   'photo_url','item_condition','defect_note','proof_uploaded_at'
// ];


//     public function request() {
//         return $this->belongsTo(StockRequest::class, 'stock_request_id');
//     }
// }


class StockRequestItem extends Model
{
    protected $fillable = [
        'stock_request_id','name','request_qty','unit','note',
        'photo_url','item_condition','defect_note','proof_uploaded_at',
        'tracking_number', // NEW
    ];

    public function request() {
        return $this->belongsTo(StockRequest::class, 'stock_request_id');
    }
}