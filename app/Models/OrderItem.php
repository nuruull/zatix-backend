<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory, HasVersion4Uuids;

    protected $fillable = ['order_id', 'ticket_id', 'quantity', 'price', 'discount', 'subtotal'];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}
