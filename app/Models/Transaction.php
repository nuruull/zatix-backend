<?php

namespace App\Models;

use App\Enum\Type\TransactionTypeEnum;
use Illuminate\Database\Eloquent\Model;
use App\Enum\Status\TransactionStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;

class Transaction extends Model
{
    use HasFactory;

    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = ['order_id', 'user_id', 'version_of_payment', 'grand_discount', 'grand_amount', 'type', 'status'];

    protected $casts = [
        'status'=> TransactionStatusEnum::class,
        'type' => TransactionTypeEnum::class,
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
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
