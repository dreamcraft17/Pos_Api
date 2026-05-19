<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BundleMenu extends Model
{
    protected $fillable = [
        'bundle_code', 'name', 'price_cents', 'enabled', 'sort', 
        'type', 'created_by', 'created_by_id'
    ];

    public function items()
    {
        return $this->hasMany(BundleMenuItem::class, 'bundle_code', 'bundle_code');
    }

    public function components()
    {
        return $this->hasMany(BundleComponent::class, 'bundle_code', 'bundle_code');
    }
}