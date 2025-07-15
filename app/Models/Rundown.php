<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Rundown extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'title',
        'description',
        'start_datetime',
        'end_datetime',
        'order',
        'is_public',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'is_public' => 'boolean',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
