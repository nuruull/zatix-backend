<?php

namespace App\Models;

use App\Enum\Status\ApprovalStatusEventEnum;
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
        // 'approval_status',
        'is_published',
        'is_public',
        'contact_phone',
        'tnc_id',
        // 'is_accepted'
    ];

    protected function casts(): array
    {
        return [
            'status' => EventStatusEnum::class,
            // 'approval_status' => ApprovalStatusEventEnum::class
        ];
    }

    public function eventOrganizer()
    {
        return $this->belongsTo(EventOrganizer::class, 'eo_id');
    }

    public function facilities()
    {
        return $this->belongsToMany(Facility::class, 'event_facilities', 'event_id', 'facility_id');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function tnc()
    {
        return $this->belongsTo(TermAndCon::class, 'tnc_id');
    }

    public function tncStatuses(){
        return $this->hasMany(TncStatus::class);
    }
}
