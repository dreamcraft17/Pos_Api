<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuVariant extends Model
{
    public $timestamps = false;

    // Tambah 'menu_id' supaya anak nempel ke parent spesifik
    protected $fillable = ['menu_id','menu_code','kind','category','size','price_cents','created_by'];

    public function menu()
    {
        return $this->belongsTo(Menu::class, 'menu_id', 'id');
    }
}
