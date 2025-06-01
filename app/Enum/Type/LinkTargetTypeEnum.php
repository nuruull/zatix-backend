<?php

namespace App\Enum\Type;

enum LinkTargetTypeEnum: string
{
    case SELF = '_self';
    case BLANK = '_blank';

    public function label(): string
    {
        return match ($this) {
            self::SELF => '_self',
            self::BLANK => '_blank',
        };
    }
}
