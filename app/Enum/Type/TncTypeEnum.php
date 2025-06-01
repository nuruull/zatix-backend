<?php

namespace App\Enum\Type;

enum TncTypeEnum : string
{
    case GENERAL = 'general';
    case EVENT = 'event';

    public function label(): string
    {
        return match ($this) {
            self::GENERAL => 'General',
            self::EVENT => 'Event',
        };
    }
}
