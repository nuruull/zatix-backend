<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ETicketsGenerated extends Notification
{
    use Queueable;

    /**
     * The order instance.
     *
     * @var \App\Models\Order
     */
    public $order;

    /**
     * Create a new notification instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('E-Ticket Anda untuk ' . $this->order->event->name . ' Sudah Terbit!')
            ->greeting('Halo, ' . $notifiable->name . '!')
            ->line('Terima kasih atas pembelian Anda. E-ticket Anda untuk event "' . $this->order->event->name . '" telah berhasil diterbitkan.')
            ->line('Nomor Pesanan: ' . $this->order->id)
            ->action('Lihat Tiket Saya', url('/my-tickets')) // Ganti dengan URL yang sesuai
            // ->attach(public_path('/path/to/your/ticket.pdf'))
            ->line('Harap tunjukkan QR Code pada e-ticket Anda di pintu masuk venue. Terima kasih!');

    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
