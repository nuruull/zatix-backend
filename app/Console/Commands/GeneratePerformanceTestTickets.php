<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\ETicket;
use Illuminate\Console\Command;

class GeneratePerformanceTestTickets extends Command
{
    /**
     * Nama dan signature dari perintah console.
     * --count: Jumlah tiket yang ingin dibuat.
     * --output: Nama file output CSV.
     */
    protected $signature = 'generate:perf-tickets {--count=1000} {--output=tickets_to_scan.csv}';

    /**
     * Deskripsi dari perintah console.
     */
    protected $description = 'Generate a large number of valid e-tickets for performance testing and export to a CSV file.';

    /**
     * Menjalankan logika perintah.
     */
    public function handle()
    {
        $count = (int) $this->option('count');
        $outputFile = $this->option('output');

        $this->info("Starting to generate {$count} e-tickets...");

        // Ambil data dasar yang dibutuhkan
        try {
            $event = Event::where('is_published', true)->firstOrFail();
            $ticketType = Ticket::where('event_id', $event->id)->firstOrFail();
            $customer = User::whereHas('roles', fn($q) => $q->where('name', 'customer'))->firstOrFail();
        } catch (\Exception $e) {
            $this->error('Please ensure you have at least one published event, one ticket type, and one customer user in your database.');
            $this->error($e->getMessage());
            return 1;
        }

        // Buka file CSV untuk ditulis
        $handle = fopen($outputFile, 'w');
        // Tulis header
        fputcsv($handle, ['ticket_code']);

        // Buat progress bar untuk visualisasi
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        for ($i = 0; $i < $count; $i++) {
            // Kita tidak perlu semua logika kompleks, cukup buat data yang valid
            $order = Order::factory()->create([
                'event_id' => $event->id,
                'user_id' => $customer->id,
                'status' => 'paid',
            ]);

            $eTicket = ETicket::factory()->create([
                'order_id' => $order->id,
                'ticket_id' => $ticketType->id,
                'user_id' => $customer->id,
            ]);

            // Tulis ticket_code ke file CSV
            fputcsv($handle, [$eTicket->ticket_code]);

            $bar->advance();
        }

        $bar->finish();
        fclose($handle);

        $this->info("\nSuccessfully generated {$count} ticket codes in '{$outputFile}'.");

        return 0;
    }
}
