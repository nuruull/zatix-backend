<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use App\Models\EventOrganizer;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class EoVerifiedNotification extends Notification
{
    use Queueable;

    public EventOrganizer $eventOrganizer;

    public function __construct(EventOrganizer $eventOrganizer)
    {
        $this->eventOrganizer = $eventOrganizer;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Selamat! Profil Event Organizer Anda Telah Terverifikasi")
            ->greeting('Halo, ' . $notifiable->name . '!')
            ->line("Kabar baik! Profil Event Organizer Anda, '{$this->eventOrganizer->name}', telah berhasil kami verifikasi.")
            ->line('Anda sekarang memiliki akses penuh untuk membuat dan mempublikasikan event di platform kami.')
            ->action('Mulai Buat Event', url('/create-event')) // Ganti dengan URL yang relevan
            ->line('Terima kasih atas kerja sama Anda.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event_organizer_id' => $this->eventOrganizer->id,
            'message' => "Profil Event Organizer Anda '{$this->eventOrganizer->name}' telah terverifikasi.",
        ];
    }
}
