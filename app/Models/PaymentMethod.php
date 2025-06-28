<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $table = 'payment_methods';
    protected $fillable = ['payment_method_category_id', 'bank_id', 'is_active', 'is_maintenance', 'priority'];

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function transactionPaymentDetails()
    {
        return $this->hasMany(TransactionPaymentDetail::class);
    }

    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class);
    }
}
