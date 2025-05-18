<?php

namespace App\Models;

use App\Enum\Status\DocumentTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    use HasFactory;
    protected $fillable = ['eo_id', 'type'];

    protected function casts(): array
    {
        return [
            'type' => DocumentTypeEnum::class,
        ];
    }

    public function eo()
    {
        return $this->belongsTo(EventOrganizer::class);
    }

    public function individualDocument()
    {
        return $this->hasOne(IndividualDocument::class);
    }

    public function organizationDocument()
    {
        return $this->hasOne(OrganizationDocument::class);
    }
}
