<?php

namespace App\Enum\Type;

enum DocumentTypeEnum : string
{
    case INDIVIDUAL = 'individual';
    case ORGANIZATION = 'organization';

    public function label(): string
    {
        return match ($this) {
            self::INDIVIDUAL => 'Individual',
            self::ORGANIZATION => 'Organization',
        };
    }
}
