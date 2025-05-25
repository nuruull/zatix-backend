<?php

namespace App\Models;

use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EventOrganizer extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = ['eo_owner_id', 'name', 'logo', 'description', 'email_eo', 'phone_no_eo', 'address_eo', 'is_doc_verified'];

    public function eo_owner()
    {
        return $this->belongsTo(User::class, 'eo_owner_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
