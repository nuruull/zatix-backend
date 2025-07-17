<?php

namespace App\Actions;

use App\Models\EventOrganizer;
use App\Notifications\EoVerifiedNotification; // Buat notifikasi baru ini

class CheckEoVerificationStatus
{
    public function execute(EventOrganizer $eventOrganizer): void
    {
        // 1. Tentukan dokumen apa saja yang wajib berdasarkan tipe EO
        $requiredDocs = [];
        if ($eventOrganizer->organizer_type === 'individual') {
            $requiredDocs = ['ktp']; // NPWP tidak wajib
        } elseif ($eventOrganizer->organizer_type === 'company') {
            $requiredDocs = ['nib', 'npwp'];
        }

        if (empty($requiredDocs)) {
            return; // Tidak ada yang perlu dicek
        }

        // 2. Ambil semua tipe dokumen yang sudah diverifikasi untuk EO ini
        $verifiedDocs = $eventOrganizer->documents()
            ->where('status', 'verified')
            ->pluck('type')
            ->toArray();

        // 3. Cek apakah dokumen yang wajib sudah ada di dalam daftar yang terverifikasi
        $isFullyVerified = empty(array_diff($requiredDocs, $verifiedDocs));

        // 4. Jika semua syarat terpenuhi, update status EO dan kirim notifikasi
        if ($isFullyVerified && $eventOrganizer->verification_status !== 'verified') {
            $eventOrganizer->update(['verification_status' => 'verified']);

            // Kirim notifikasi "Selamat, akun Anda telah terverifikasi!"
            $eventOrganizer->eo_owner->notify(new EoVerifiedNotification($eventOrganizer));
        }
    }
}
