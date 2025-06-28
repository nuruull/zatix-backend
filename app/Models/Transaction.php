<?php

namespace App\Models;

use App\Enum\Status\TransactionStatusEnum;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory, HasVersion4Uuids;

    protected $fillable = ['order_id', 'user_id', 'version_of_payment', 'grand_discount', 'grand_amount', 'type', 'status'];

    protected $casts = [
        'status'=> TransactionStatusEnum::class
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function paymentDetail()
    {
        return $this->hasOne(TransactionPaymentDetail::class);
    }

    public function afterCost()
    {
        return $this->hasOne(TransactionAfterCost::class);
    }
}
