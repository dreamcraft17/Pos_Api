<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $table = 'payment_methods';

    protected $fillable = ['code','name','enabled','sort','created_by'];

    protected $casts = [
        'enabled' => 'boolean',
        'sort'    => 'integer',
    ];

    // MATIKAN timestamps kalau tabel tidak punya created_at/updated_at
    public $timestamps = false;

    public function scopeVisibleFor($query, $userId = null)
    {
        $userId ??= optional(auth()->user())->id;

        return $query->where(function ($q) use ($userId) {
            $q->whereNull('created_by');
            if ($userId) {
                $q->orWhere('created_by', $userId);
            }
        });
    }
}

