<?php

namespace App\Models;

use App\Enum\Status\ApprovalStatusEventEnum;
use App\Enum\Status\EventStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Event extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'eo_id',
        'tnc_id',
        'name',
        'description',
        'start_date',
        'start_time',
        'end_date',
        'end_time',
        'location',
        'status',
        'contact_phone',
        'is_published',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'status' => EventStatusEnum::class,
            // 'approval_status' => ApprovalStatusEventEnum::class
        ];
    }

    //create log acativity for event model
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->setDescriptionForEvent(function (string $eventName) {
                $eoName = $this->eventOrganizer->name ?? 'Unknown EO';
                return "Event '{$this->name}' by '{$eoName}' has been {$eventName}";
            })
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
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

    public function tncStatuses()
    {
        return $this->hasMany(TncStatus::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function rundowns()
    {
        return $this->hasMany(Rundown::class)->orderBy('start_datetime', 'asc')->orderBy('order', 'asc');
    }

    public function financialTransactions()
    {
        return $this->hasMany(FinancialTransaction::class);
    }
}
