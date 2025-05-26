<?php

namespace App\Models;

use App\Enum\Status\DocumentStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Document extends Model
{
    use HasFactory;

    protected $fillable = ['documentable_id', 'documentable_type', 'type', 'file', 'number', 'name', 'address', 'status', 'rejected_reason'];

    protected $casts = [
        'status'=> DocumentStatusEnum::class,
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }
}
