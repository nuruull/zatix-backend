<?php

namespace App\Models;

use App\Enum\Status\DocumentStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\LogOptions;

class Document extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['documentable_id', 'documentable_type', 'type', 'file', 'number', 'name', 'address', 'status', 'reason_rejected'];

    protected $casts = [
        'status'=> DocumentStatusEnum::class,
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->setDescriptionForEvent(function(string $eventName) {
                $ownerName = $this->documentable->name ?? 'Unknown Owner';
                return "A document of type '{$this->type}' for '{$ownerName}' has been {$eventName}";
            })
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
