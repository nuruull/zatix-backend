<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;
use App\Services\WaitingRoomService;

class ProcessWaitingRoomQueue extends Command
{
    protected $signature = 'queue:process-waiting-room';
    protected $description = 'Lets users from the waiting room into the checkout process.';

    public function handle(WaitingRoomService $waitingRoom)
    {
        $this->info('Processing waiting rooms...');
        $activeEvents = Event::where('status', 'active')->whereHas('tickets', function ($q) {
            $q->where('end_date', '>=', now());
        })->get();

        foreach ($activeEvents as $event) {
            $slotsToFill = $waitingRoom->getAvailableSlots($event);

            if ($slotsToFill > 0) {
                $this->info("Event '{$event->name}' has {$slotsToFill} slots. Letting users in...");
                $waitingRoom->letNextUsersIn($event, $slotsToFill);
            }
        }
        $this->info('Finished processing.');
        return 0;
    }
}
