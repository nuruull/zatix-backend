<?php

namespace App\Models;

use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class EventOrganizer extends Model
{
    use HasFactory, HasRoles, LogsActivity;

    protected $fillable = ['eo_owner_id', 'name', 'logo', 'description', 'email_eo', 'phone_no_eo', 'address_eo', 'is_doc_verified'];

    //create log acativity for event organizer model
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->setDescriptionForEvent(fn(string $eventName) => "Event Organizer '{$this->name}' has been {$eventName}")
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function eo_owner()
    {
        return $this->belongsTo(User::class, 'eo_owner_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function members() {
        return $this->belongsToMany(User::class, 'event_organizer_users');
    }
}
