<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\TermAndCon;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TncStatus>
 */
class TncStatusFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Secara default, hubungkan ke User dan T&C yang baru dibuat.
            // Anda bisa menimpanya di dalam test jika perlu.
            'user_id' => User::factory(),
            'tnc_id' => TermAndCon::factory(),

            // Secara default, status persetujuan ini belum terikat ke event manapun.
            'event_id' => null,

            // Secara default, kita asumsikan status ini adalah untuk persetujuan.
            'is_accepted' => true,

            // Berdasarkan error sebelumnya, kolom ini wajib diisi.
            // Jika is_accepted true, maka accepted_at adalah waktu sekarang.
            'accepted_at' => now(),
        ];
    }

    /**
     * State untuk kondisi di mana T&C belum disetujui.
     */
    public function notAccepted(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'is_accepted' => false,
                'accepted_at' => null,
            ];
        });
    }

    /**
     * State untuk kondisi di mana T&C sudah terikat ke sebuah event.
     */
    public function forEvent(Event $event): Factory
    {
        return $this->state(function (array $attributes) use ($event) {
            return [
                'event_id' => $event->id,
            ];
        });
    }
}
