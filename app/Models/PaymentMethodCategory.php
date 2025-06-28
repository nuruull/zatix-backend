<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethodCategory extends Model
{
    use HasFactory;

    protected $table = 'payment_method_categories';

    protected $fillable = ['name', 'is_active'];

    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class);
    }
}
