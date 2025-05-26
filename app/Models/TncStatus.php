<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TncStatus extends Model
{
    use HasFactory;

    protected $fillable = ['tnc_id', 'user_id', 'event_id', 'accepted_at'];

    protected $casts = [
        'accepted_at'=> 'datetime',
    ];

    public function termAndCon()
    {
        return $this->belongsTo(TermAndCon::class);
    }

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function event(){
        return $this->belongsTo(Event::class);
    }
}
