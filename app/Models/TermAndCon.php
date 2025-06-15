<?php

namespace App\Models;

use App\Enum\Type\TncTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TermAndCon extends Model
{
    use HasFactory, LogsActivity;

    protected $table = "terms_and_cons";
    protected $fillable = ['type', 'content'];

    protected function casts(): array
    {
        return [
            'type' => TncTypeEnum::class
        ];
    }

    //create log activity for tnc model
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->setDescriptionForEvent(fn(string $eventName) => "TNC '{$this->name}' has been {$eventName}")
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'tnc_id');
    }

    public function tncStatuses()
    {
        return $this->hasMany(TncStatus::class, 'tnc_id');
    }
}
