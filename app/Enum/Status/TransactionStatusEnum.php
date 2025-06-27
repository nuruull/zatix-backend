<?php

namespace App\Enum\Status;

enum TransactionStatusEnum: string
{
    case SETTLEMENT = 'settlement';
    case CAPTURE = 'capture';
    case PENDING = 'pending';
    case DENY = 'deny';
    case CANCEL = 'cancel';
    case EXPIRE = 'expire';
    case ACCEPT = 'accept';
    case CHALLENGE = 'challenge';
    case REFUND = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::SETTLEMENT => 'Pembayaran Berhasil',
            self::CAPTURE => 'Pembayaran Tertangkap (Menunggu Review)',
            self::PENDING => 'Menunggu Pembayaran',
            self::DENY => 'Pembayaran Ditolak',
            self::CANCEL => 'Pembayaran Dibatalkan',
            self::EXPIRE => 'Pembayaran Kadaluarsa',
            self::ACCEPT => 'Disetujui (Aman)',
            self::CHALLENGE => 'Perlu Diperiksa (Challenge)',
            self::REFUND => 'Dana Dikembalikan',
        };
    }
}
