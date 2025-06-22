<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use App\Models\EventOrganizer;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class NewVerificationRequest extends Notification
{
    use Queueable;

    public EventOrganizer $organizer;

    /**
     * Create a new notification instance.
     */
    public function __construct(EventOrganizer $organizer)
    {
        $this->organizer = $organizer;
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

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $eoName = $this->organizer->name;

        $documentList = $this->organizer->documents->pluck('type')->implode(', ');

        $adminUrl = url('/' . $this->organizer->id);

        return (new MailMessage)
            ->subject("Pengajuan Verifikasi Profil Baru dari {$eoName}")
            ->greeting('Halo Admin,')
            ->line("Ada pengajuan verifikasi profil Event Organizer baru yang membutuhkan perhatian Anda.")
            ->line("Nama Event Organizer: **{$eoName}**")
            ->line("Tipe Organizer: **" . ucfirst($this->organizer->organizer_type->value) . "**")
            ->line("Dokumen yang Diajukan: **{$documentList}**")
            ->action('Tinjau Pengajuan Profil', $adminUrl)
            ->line('Silakan login ke panel admin untuk melakukan verifikasi.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'organizer_id' => $this->organizer->id,
            'organizer_name' => $this->organizer->name,
            'message' => "Pengajuan verifikasi profil baru dari '{$this->organizer->name}'.",
            'admin_url' => '/' . $this->organizer->id, // Sesuaikan URL admin Anda
        ];
    }
}
