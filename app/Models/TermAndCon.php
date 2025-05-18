<?php

namespace App\Models;

use App\Enum\Status\TncTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TermAndCon extends Model
{
    use HasFactory;

    protected $table = "terms_and_cons";
    protected $fillable = ['type', 'content'];

    protected function casts(): array
    {
        return [
            'type' => TncTypeEnum::class
        ];
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'tnc_id');
    }

    public function tncStatuses()
    {
        return $this->hasMany(TncStatus::class, 'tnc_id');
    }

    // public function acceptedUsers()
    // {
    //     return $this->belongsToMany(User::class, 'tnc_status', 'tnc_id', 'user_id')
    //         ->withPivot('accepted_at')
    //         ->withTimestamps();
    // }
}
