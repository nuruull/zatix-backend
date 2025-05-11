<?php

namespace App\Enum\Status;

enum EventStatusEnum: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::ACTIVE => 'Active',
            self::COMPLETED => 'Completed',
        };
    }
}
