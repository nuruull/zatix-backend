<?php

namespace App\Models;

use App\Enum\Type\LinkTargetTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Carousel extends Model
{
    // add commit carousel model
    use HasFactory;

    protected $fillable = ['image', 'caption', 'link_url', 'link_target', 'is_active'];

    protected function casts(): array
    {
        return [
            'link_target' => LinkTargetTypeEnum::class,
        ];
    }
}
