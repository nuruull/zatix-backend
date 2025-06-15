<?php

namespace App\Notifications;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentStatusUpdated extends Notification
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
        $status = $this->document->status->value;
        $eoName = $this->document->documentable->name; //EO Name

        $mailMessage = (new MailMessage)
            ->subject('Update Verifikasi Dokumen untuk {$eoName}');

        if ($status == 'verified') {
            $mailMessage
                ->greeting('Selamat!')
                ->line("Dokumen '{$this->document->type}' Anda untuk {$eoName} telah disetujui.")
                ->line('Anda sekarang dapat melanjutkan untuk mempublikasikan event.')
                ->action('Lihat Dashboard', url('/test'));
        } elseif ($status == 'rejected') {
            $mailMessage
                ->greeting('Pemberitahuan Verifikasi Dokumen')
                ->line("Dokumen '{$this->document->type}' Anda untuk {$eoName} ditolak.")
                ->line("Alasan: " . $this->document->reason_rejected)
                ->line('Silakan periksa dan unggah kembali dokumen yang sesuai.')
                ->action('Periksa Dokumen', url('/test'));
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
            'message' => "Status dokumen '{$this->document->type}' Anda telah diperbarui menjadi '{$this->document->status->value}'."
        ];
    }
}
