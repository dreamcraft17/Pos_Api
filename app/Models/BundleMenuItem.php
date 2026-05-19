<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BundleMenuItem extends Model
{
    public $timestamps = false;
    
    protected $fillable = [
        'bundle_code', 'menu_code', 'menu_name', 'menu_type', 
        'qty', 'price_rupiah', 'created_by'
    ];

    public function menu()
    {
        return $this->belongsTo(Menu::class, 'menu_code', 'code');
    }

    public function bundle()
    {
        return $this->belongsTo(BundleMenu::class, 'bundle_code', 'bundle_code');
    }
}