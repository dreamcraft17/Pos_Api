<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderType extends Model
{
    protected $fillable = ['code','name','enabled','sort','created_by'];
}
