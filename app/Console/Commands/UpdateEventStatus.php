<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\Event;
use Illuminate\Console\Command;
use App\Enum\Status\EventStatusEnum;

class UpdateEventStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:update-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deactivates events that have passed their end date.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to deactivate past events...');

        $deactivatedCount = Event::where('status', EventStatusEnum::ACTIVE->value)
            ->where('end_date', '<', Carbon::today())
            ->update([
                'status' => EventStatusEnum::INACTIVE->value,
                'is_published' => false,
                'is_public' => false,
            ]);

        if ($deactivatedCount > 0) {
            $this->info("Successfully deactivated {$deactivatedCount} past event(s).");
        } else {
            $this->info("No active events needed to be deactivated.");
        }

        $this->info('Process finished.');
        return 0;
    }
}
