<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BundleComponent extends Model
{
    public $timestamps = false;
    
    protected $fillable = [
        'bundle_code', 'product_sku', 'qty', 'created_by'
    ];

    public function bundle()
    {
        return $this->belongsTo(BundleMenu::class, 'bundle_code', 'bundle_code');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_sku', 'sku');
    }
}