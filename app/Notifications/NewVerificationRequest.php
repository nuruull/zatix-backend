<?php

namespace App\Notifications;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewVerificationRequest extends Notification
{
    use Queueable;

    public $document;

    /**
     * Create a new notification instance.
     */
    public function __construct(Document $document)
    {
        $this->document = $document;
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
        $eoName = $this->document->documentable->name ?? 'Event Organizer';
        $documentType = $this->document->type;

        $adminUrl = url('/test' . $this->document->id);

        return (new MailMessage)
            ->subject("Pengajuan Verifikasi Dokumen Baru dari {$eoName}")
            ->greeting('Halo Admin,')
            ->line("Ada pengajuan verifikasi dokumen baru yang membutuhkan perhatian Anda.")
            ->line("Event Organizer: **{$eoName}**")
            ->line("Jenis Dokumen: **{$documentType}**")
            ->action('Tinjau Pengajuan Dokumen', $adminUrl)
            ->line('Silakan login ke panel admin untuk melakukan verifikasi.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $eoName = $this->document->documentable->name ?? 'Event Organizer';

        return [
            'document_id' => $this->document->id,
            'eo_name' => $eoName,
            'message' => "Pengajuan verifikasi baru dari '{$eoName}' untuk dokumen '{$this->document->type}'.",
            'admin_url' => '/test' . $this->document->id,
        ];
    }
}
