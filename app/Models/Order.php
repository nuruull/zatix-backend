<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasVersion4Uuids, HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['user_id', 'event_id', 'net_amount', 'status', 'snap_token'];

    protected $casts = [
        'net_amount' => 'integer',
        'status' => OrderStatusEnum::class,
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function event(){
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
}
