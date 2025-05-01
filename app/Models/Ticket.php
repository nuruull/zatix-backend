<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = ['event_id', 'ticket_type_id', 'name', 'price', 'stock', 'limit', 'start_date', 'end_date'];

    public function event() {
        return $this->belongsTo(Event::class);
    }

    public function ticketType(){
        return $this->belongsTo(TicketType::class);
    }
}
