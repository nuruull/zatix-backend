<?php

namespace App\Models;

use App\Enum\Status\EventStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'eo_id',
        'name',
        'description',
        'start_date',
        'start_time',
        'end_date',
        'end_time',
        'location',
        'status',
        'approval_status',
        'contact_phone',
    ];

    protected function casts(): array
    {
        return [
            'status' => EventStatusEnum::class
        ];
    }

    public function eventOrganizer()
    {
        return $this->belongsTo(EventOrganizer::class, 'eo_id');
    }

    public function facilities()
    {
        return $this->belongsToMany(Facility::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function tnc()
    {
        return $this->belongsTo(TermAndCon::class, 'tnc_id');
    }
}
