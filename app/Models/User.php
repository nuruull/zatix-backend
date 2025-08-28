<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Notifications\CustomResetPasswordNotification;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'created_by',
        'name',
        'email',
        'password',
        'email_verified_at'
    ];

    /**
     * The guard that this model uses.
     *
     * @var string
     */
    protected $guard_name = 'api';

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new CustomResetPasswordNotification($token));
    }

    //create log acitivity for user
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email'])
            ->setDescriptionForEvent(fn(string $eventName) => "User has been {$eventName}");
    }

    public function eventOrganizer()
    {
        return $this->hasOne(EventOrganizer::class, 'eo_owner_id', 'id');
    }

    public function demoRequest()
    {
        return $this->hasOne(DemoRequest::class);
    }

    public function otpCode()
    {
        return $this->hasOne(OtpCode::class);
    }

    public function tncStatuses()
    {
        return $this->hasMany(TncStatus::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentableq');
    }

    public function teams()
    {
        return $this->belongsToMany(EventOrganizer::class, 'event_organizer_users', 'eo_id', 'user_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function eTickets()
    {
        return $this->hasMany(ETicket::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function recordedTransactions()
    {
        return $this->hasMany(FinancialTransaction::class, 'recorded_by_user_id');
    }

    public function events()
    {
        return $this->belongsToMany(Event::class, 'event_staff');
    }

    public function bookmarkedEvents()
    {
        return $this->belongsToMany(Event::class, 'bookmarked_events')->withTimestamps();
    }
}
