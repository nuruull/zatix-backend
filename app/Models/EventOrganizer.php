<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventOrganizer extends Model
{
    use HasFactory;

    protected $fillable = ['eo_owner_id', 'name', 'logo', 'description'];

    public function eo_owner() {
        return $this->belongsTo(User::class, 'eo_owner_id', 'user_id');
    }
}
