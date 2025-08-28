<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use App\Notifications\TicketSaleEndingNotification;

class NotifyUsersOfExpiringTickets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:expiring-tickets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send notifications to users about bookmarked events with expiring ticket sales.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Mulai memeriksa event dengan penjualan tiket yang akan berakhir...');

        $notificationThreshold = now()->addDay();

        $expiringEvents = Event::whereHas('tickets', function ($query) use ($notificationThreshold) {
            $query->where('end_date', '<=', $notificationThreshold)
                ->where('end_date', '>', now());
        })->get();

        if ($expiringEvents->isEmpty()) {
            $this->info('Tidak ada event yang memenuhi kriteria. Proses selesai.');
            return 0;
        }

        $this->info("Ditemukan {$expiringEvents->count()} event yang akan berakhir.");

        foreach ($expiringEvents as $event) {
            $bookmarkedUsers = $event->bookmarkedByUsers;

            if ($bookmarkedUsers->isNotEmpty()) {
                $this->info("- Mengirim notifikasi untuk event '{$event->name}' ke {$bookmarkedUsers->count()} user.");

                Notification::send($bookmarkedUsers, new TicketSaleEndingNotification($event));
            } else {
                $this->info("- Event '{$event->name}' tidak memiliki bookmark.");
            }
        }

        $this->info('Semua notifikasi berhasil dikirim. Proses selesai.');
        return 0;
    }
}
