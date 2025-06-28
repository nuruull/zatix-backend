<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionAfterCost extends Model
{
    use HasFactory;

    protected $table = 'transaction_after_costs';

    protected $fillable = ['transaction_id', 'transaction_payment_id', 'main_cost', 'additional_cost', 'grand_total'];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function transactionPaymentDetail()
    {
        return $this->belongsTo(TransactionPaymentDetail::class, 'transaction_payment_id');
    }
}
