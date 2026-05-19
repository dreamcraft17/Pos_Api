<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    protected $fillable = [
        'code','name','price_rupiah','image_url','enabled','sort','created_by','type',
    ];

    // Relasi baru via menu_id (bukan menu_code)
    public function items()
    {
        return $this->hasMany(MenuItem::class, 'menu_id', 'id');
    }

    public function variants()
    {
        return $this->hasMany(MenuVariant::class, 'menu_id', 'id');
    }
}
