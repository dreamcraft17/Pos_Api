<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuCode extends Model
{
    protected $fillable = ['menu_id','code'];

    public function menu() {
        return $this->belongsTo(Menu::class);
    }
}
