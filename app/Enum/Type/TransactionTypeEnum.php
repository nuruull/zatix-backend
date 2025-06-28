<?php

namespace App\Enum\Type;

enum TransactionTypeEnum: string
{
    case TRANSFER = 'transfer';
    case CASH = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::TRANSFER => 'Transfer',
            self::CASH => 'Cash',
        };
    }
}
