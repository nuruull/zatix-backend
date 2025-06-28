<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionPaymentDetail extends Model
{
    use HasFactory;

    protected $table = 'transaction_payment_detail';

    protected $fillable = ['transaction_id', 'payment_method_id', 'va_number', 'bill_key', 'biller_code', 'qris_url', 'expiry_at'];

    protected $casts = [
        'expiry_at' => 'datetime'
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function afterCost()
    {
        return $this->hasOne(TransactionAfterCost::class);
    }
}
