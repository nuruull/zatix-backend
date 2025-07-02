<?php

namespace App\Models;

use App\Models\ETicket;
use App\Enum\Status\OrderStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;

class Order extends Model
{
    use HasVersion4Uuids, HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['user_id', 'event_id', 'gross_amount', 'discount_amount', 'tax_amount', 'net_amount', 'status'];

    protected $casts = [
        'status' => OrderStatusEnum::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function eTickets()
    {
        return $this->hasMany(ETicket::class);
    }

    public function vouchers()
    {
        return $this->belongsToMany(Voucher::class, 'order_voucher')
            ->withPivot('discount_amount');
    }
}
