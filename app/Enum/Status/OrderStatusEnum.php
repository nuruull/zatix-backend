<?php

namespace App\Enum\Status;

enum OrderStatusEnum: string
{
    case UNPAID = 'unpaid';
    case PAID = 'paid';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'unpaid',
            self::PAID => 'paid',
            self::EXPIRED => 'expired',
            self::CANCELLED => 'cancelled',
        };
    }
}
