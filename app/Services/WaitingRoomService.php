<?php

namespace App\Services;

use App\Models\User;
use App\Models\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;


class WaitingRoomService
{
    // Kapasitas maksimum user yang boleh aktif di checkout per event
    private const CAPACITY = 500;
    // Berapa lama sesi seorang user di ruang checkout (detik)
    private const SESSION_DURATION = 600; // 10 menit
    // Rata-rata waktu tunggu per orang (detik) untuk estimasi
    private const AVG_WAIT_PER_PERSON = 15; // 15 detik

    private function getActiveUsersKey(Event $event): string
    {
        return "event:{$event->id}:active_users";
    }

    private function getQueueKey(Event $event): string
    {
        return "event:{$event->id}:queue";
    }

    // Cek apakah user sudah diizinkan masuk
    public function isAllowedToProceed(Event $event, User $user): bool
    {
        return Redis::sismember($this->getActiveUsersKey($event), $user->id);
    }

    // Cek apakah masih ada kapasitas
    public function hasCapacity(Event $event): bool
    {
        $key = $this->getActiveUsersKey($event);
        $count = Redis::scard($key);

        // LOG UNTUK MELIHAT HITUNGAN
        Log::info("CHECKING CAPACITY for key [{$key}]. Current count: {$count}. Capacity: " . self::CAPACITY);

        return $count < self::CAPACITY;
    }

    // Izinkan user masuk ke ruang checkout
    public function allowUserToProceed(Event $event, User $user): void
    {
        $key = $this->getActiveUsersKey($event);
        Redis::sadd($key, $user->id);

        // LOG UNTUK MELIHAT PENAMBAHAN USER
        $newCount = Redis::scard($key);
        Log::info("ADDING USER {$user->id} to key [{$key}]. New count: {$newCount}");

        Redis::expire($key, self::SESSION_DURATION);
    }

    // Hapus user dari ruang checkout (setelah selesai order / sesi habis)
    public function removeUserFromActive(Event $event, User $user): void
    {
        Redis::srem($this->getActiveUsersKey($event), $user->id);
    }

    // Tambahkan user ke antrian
    public function addUserToQueue(Event $event, User $user): array
    {
        $queueKey = $this->getQueueKey($event);
        // Gunakan timestamp sebagai score untuk urutan antrian (First-In, First-Out)
        Redis::zadd($queueKey, time(), $user->id);

        $position = Redis::zrank($queueKey, $user->id) + 1;
        $estimatedTime = $this->getEstimatedWaitTime($position);

        return ['position' => $position, 'estimated_wait_time_minutes' => $estimatedTime];
    }

    // Dapatkan estimasi waktu tunggu dalam menit
    public function getEstimatedWaitTime(int $position): int
    {
        return ceil(($position * self::AVG_WAIT_PER_PERSON) / 60);
    }

    // Dapatkan jumlah slot yang kosong
    public function getAvailableSlots(Event $event): int
    {
        $activeCount = Redis::scard($this->getActiveUsersKey($event));
        return self::CAPACITY - $activeCount;
    }

    // Izinkan sejumlah user berikutnya dari antrian untuk masuk
    public function letNextUsersIn(Event $event, int $count): void
    {
        $queueKey = $this->getQueueKey($event);
        $activeUsersKey = $this->getActiveUsersKey($event);

        // Ambil user ID dari paling depan antrian
        $usersToLetIn = Redis::zrange($queueKey, 0, $count - 1);

        if (!empty($usersToLetIn)) {
            // Hapus mereka dari antrian
            Redis::zremrangebyrank($queueKey, 0, $count - 1);

            // Tambahkan mereka ke daftar user aktif
            foreach ($usersToLetIn as $userId) {
                Redis::sadd($activeUsersKey, $userId);
            }
            // Kirim notifikasi ke user-user ini (misal via WebSocket) bahwa giliran mereka tiba.
        }
    }
}
