<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'code', 'discount_type', 'discount_value', 'max_amount', 'usage_limit', 'valid_until', 'is_active'];

    protected $casts = [
        'valid_until' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_voucher')
            ->withPivot('discount_amount');
    }
}
