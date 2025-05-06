<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RoleUpgradedNotification extends Notification
{
    use Queueable;

    protected $newRole;

    /**
     * Create a new notification instance.
     */
    public function __construct($newRole)
    {
        $this->newRole = $newRole;
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
        return (new MailMessage)
            ->subject('Upgrade Role Akun')
            ->greeting('Selamat!')
            ->line('Role akun Anda telah diupgrade menjadi ' . $this->newRole);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Role Anda telah diupgrade ke ' . $this->newRole,
            'url' => '/',
        ];
    }
}
