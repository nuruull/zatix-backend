<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
use App\Enum\Type\LinkTargetTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Carousel extends Model
{
    // add commit carousel model
    use HasFactory, LogsActivity;

    protected $fillable = ['image', 'title', 'caption', 'link_url', 'link_target', 'order', 'is_active'];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'link_target' => LinkTargetTypeEnum::class,
        ];
    }

    public function getImageUrlAttribute(): ?string
    {
        if ($this->image) {
            return Storage::url($this->image);
        }
        return null;
    }

    //create log acativity for carousel model
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            //Catat hanya perubahan pada field-field ini
            ->logOnly([
                'image',
                'caption',
                'link_url',
                'link_target',
                'is_active',
            ])
            ->setDescriptionForEvent(function (string $eventName) { //Buat deskripsi log yang lebih informatif
                $caption = $this->caption ? "'{$this->caption}'" : '';
                return "Carousel slide {$caption} has been {$eventName}";
            })
            ->logOnlyDirty() //Hanya catat jika ada field yang benar-benar berubah
            ->dontSubmitEmptyLogs(); //Mencegah pembuatan log kosong
    }

}
