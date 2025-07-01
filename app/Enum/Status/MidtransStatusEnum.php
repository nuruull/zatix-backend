<?php

namespace App\Enum\Status;

enum MidtransStatusEnum
{
    case SETTLEMENT = 'settlement';
    case CAPTURE = 'capture';
    case PENDING = 'pending';
    case DENY = 'deny';
    case CANCEL = 'cancel';
    case EXPIRE = 'expire';

    // --- STATUS FRAUD (KHUSUS KARTU KREDIT) ---
    case ACCEPT = 'accept';
    case CHALLENGE = 'challenge';

    // --- STATUS LAIN ---
    case REFUND = 'refund';
    case PARTIAL_REFUND = 'partial_refund';
    case CHARGEBACK = 'chargeback';
    case PARTIAL_CHARGEBACK = 'partial_chargeback';

    /**
     * Method utama untuk menentukan status pesanan internal berdasarkan notifikasi Midtrans.
     */
    public static function getOrderStatus(string $transactionStatus, ?string $fraudStatus): ?OrderStatusEnum
    {
        // Kasus 1: Pembayaran Kartu Kredit
        if ($transactionStatus === self::CAPTURE->value) {
            if ($fraudStatus === self::ACCEPT->value) {
                return OrderStatusEnum::PAID;
            }
            if ($fraudStatus === self::CHALLENGE->value) {
                return OrderStatusEnum::UNPAID;
            }
        }

        // Kasus 2: Pembayaran Berhasil (Non-Kartu Kredit)
        if ($transactionStatus === self::SETTLEMENT->value) {
            return OrderStatusEnum::PAID;
        }

        // Kasus 3: Pembayaran Gagal atau Dibatalkan
        if (in_array($transactionStatus, [self::DENY->value, self::CANCEL->value])) {
            return OrderStatusEnum::CANCELLED;
        }

        // Kasus 4: Pembayaran Kadaluarsa
        if ($transactionStatus === self::EXPIRE->value) {
            return OrderStatusEnum::EXPIRED;
        }

        // Untuk status lain seperti 'pending', kita tidak melakukan apa-apa.
        return null;
    }
}
