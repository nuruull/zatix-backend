<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;

class Eticket extends Model
{
    use HasFactory, HasVersion4Uuids;

    protected $table = 'e_tickets';

    protected $fillable = ['ticket_code', 'order_id', 'user_id', 'ticket_id', 'attendee_name', 'checked_in_at',];

    protected $casts = [
        'checked_in_at'=> 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}
