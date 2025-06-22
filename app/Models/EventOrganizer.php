<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
use App\Enum\Type\OrganizerTypeEnum;
use Spatie\Permission\Traits\HasRoles;
use App\Enum\Status\DocumentStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EventOrganizer extends Model
{
    use HasFactory, HasRoles, LogsActivity;

    protected $fillable = ['eo_owner_id', 'organizer_type', 'name', 'logo', 'description', 'email_eo', 'phone_no_eo', 'address_eo', 'is_doc_verified'];

    protected $casts = [
        'organizer_type' => OrganizerTypeEnum::class,
    ];

    protected $appends = ['is_verified'];

    public function getIsVerifiedAttribute(): bool
    {
        $requiredDocs = ($this->organizer_type === OrganizerTypeEnum::INDIVIDUAL)
            ? ['ktp']
            : ['npwp', 'nib'];

        $verifiedDocs = $this->documents()
            ->where('status', DocumentStatusEnum::VERIFIED)
            ->pluck('type');
        return count(array_intersect($requiredDocs, $verifiedDocs->all())) === count($requiredDocs);
    }

    public function hasUploadedRequiredDocuments(): bool
    {
        $requiredDocs = ($this->organizer_type === OrganizerTypeEnum::INDIVIDUAL)
            ? ['ktp']
            : ['npwp', 'nib'];

        $uploadedDocs = $this->documents()->pluck('type');

        $missingDocs = array_diff($requiredDocs, $uploadedDocs->all());

        return empty($missingDocs);
    }

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

    public function events()
    {
        return $this->hasMany(Event::class, 'eo_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'event_organizer_users', 'eo_id', 'user_id');
    }
}
