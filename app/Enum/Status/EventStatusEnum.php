<?php

namespace App\Enum\Status;

enum EventStatusEnum: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case ARCHIVE = 'archive';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::ARCHIVE => 'Archive',
        };
    }
}
