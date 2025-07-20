<?php

namespace App\Notifications;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use App\Enum\Status\DocumentStatusEnum;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class DocumentStatusUpdated extends Notification
{
    use Queueable;
    public Document $document;

    /**
     * Create a new notification instance.
     */
    public function __construct(Document $document)
    {
        $this->document = $document->loadMissing('documentable');
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
        $eoName = $this->document->documentable->name;

        // Inisialisasi MailMessage di awal
        $mailMessage = (new MailMessage)
            ->subject("Update Verifikasi Dokumen untuk {$eoName}"); // Gunakan kutip ganda

        // Gunakan perbandingan Enum untuk keamanan tipe
        if ($this->document->status === DocumentStatusEnum::VERIFIED) {
            $mailMessage
                ->greeting('Selamat!')
                ->line("Dokumen '{$this->document->type}' Anda untuk {$eoName} telah disetujui.")
                ->line('Tim kami akan meninjau kelengkapan dokumen Anda secara keseluruhan.')
                ->action('Lihat Dashboard', url('/dashboard')); // Ganti dengan URL yang relevan
        }

        if ($this->document->status === DocumentStatusEnum::REJECTED) {
            $mailMessage
                ->greeting('Pemberitahuan Verifikasi Dokumen')
                ->line("Mohon maaf, dokumen '{$this->document->type}' Anda untuk {$eoName} ditolak.")
                ->line("Alasan: " . $this->document->reason_rejected)
                ->line('Silakan periksa dan unggah kembali dokumen yang sesuai.')
                ->action('Periksa Dokumen', url('/informasi-legal')); // Ganti dengan URL yang relevan
        }

        return $mailMessage->line('Terima kasih telah menggunakan aplikasi kami!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'document_id' => $this->document->id,   
            'document_type' => $this->document->type,
            'status' => $this->document->status->value,
            'message' => "Status dokumen '{$this->document->type}' Anda telah diperbarui menjadi '{$this->document->status->value}'.",
        ];
    }
}
