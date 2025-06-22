<?php

namespace App\Enum\Type;

enum OrganizerTypeEnum : string
{
    case INDIVIDUAL = 'individual';
    case COMPANY = 'company';

    public function label(): string
    {
        return match ($this) {
            self::INDIVIDUAL => 'Individual',
            self::COMPANY => 'Company',
        };
    }
}
