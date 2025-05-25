<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class EventOrganizer extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = ['eo_owner_id', 'name', 'logo', 'description', 'email_eo', 'phone_no_eo', 'address_eo', 'is_doc_verified'];

    public function eo_owner()
    {
        return $this->belongsTo(User::class, 'eo_owner_id');
    }

    public function documentType()
    {
        return $this->hasOne(DocumentType::class);
    }
}
