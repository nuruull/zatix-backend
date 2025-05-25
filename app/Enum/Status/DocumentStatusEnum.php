<?php

namespace App\Enum\Status;

enum DocumentStatusEnum: string
{
    case PENDING = 'pending';
    case VERIFIED= 'verified';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::VERIFIED=> 'Verified',
            self::REJECTED => 'Rejected',
        };
    }
}
