<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;

class OrderVoucher extends Model
{
    use HasFactory, HasVersion4Uuids;

    protected $fillable = ['voucher_id', 'order_id', 'discount_amount_applied'];
}
