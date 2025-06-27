<?php

namespace App\Models;

use App\Enum\Status\TransactionStatusEnum;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory, HasVersion4Uuids;

    protected $fillable = ['order_id', 'version_of_payment', 'status', 'va_number', 'qris', 'bill_key', 'biller_code'];

    protected $casts = [
        'status'=> TransactionStatusEnum::class
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
