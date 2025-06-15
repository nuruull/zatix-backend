<?php

namespace App\Models;

use App\Enum\Type\LinkTargetTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Carousel extends Model
{
    // add commit carousel model
    use HasFactory, LogsActivity;

    protected $fillable = ['image', 'caption', 'link_url', 'link_target', 'is_active'];

    protected function casts(): array
    {
        return [
            'link_target' => LinkTargetTypeEnum::class,
        ];
    }

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
