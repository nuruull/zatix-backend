<?php

namespace App\Enum\Status;

enum DocumentTypeEnum
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
