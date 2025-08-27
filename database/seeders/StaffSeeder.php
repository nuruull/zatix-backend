<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Event;
use App\Models\EventOrganizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class StaffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $eventOrganizer = EventOrganizer::findOrFail(1);
            $event = Event::findOrFail(1);

            if ($event->eo_id !== $eventOrganizer->id) {
                $this->command->error("Event with ID {$event->id} does not belong to Event Organizer with ID {$eventOrganizer->id}.");
                return;
            }

            $eoOwnerUser = User::where('email', 'eoowner@zatix.com')->firstOrFail();
            $picUser = User::where('email', 'pic@zatix.com')->firstOrFail();
            $financeUser = User::where('email', 'finance@zatix.com')->firstOrFail();
            $crewUser = User::where('email', 'crew@zatix.com')->firstOrFail();
            $cashierUser = User::where('email', 'cashier@zatix.com')->firstOrFail();

            User::where('email', 'pic@zatix.com')->update(['created_by' => $eoOwnerUser->id]);

            User::whereIn('email', [
                'finance@zatix.com',
                'crew@zatix.com',
                'cashier@zatix.com'
            ])->update(['created_by' => $picUser->id]);

            $staffMembers = [
                ['user' => $picUser, 'role' => 'event-pic'],
                ['user' => $financeUser, 'role' => 'finance'],
                ['user' => $crewUser, 'role' => 'crew'],
                ['user' => $cashierUser, 'role' => 'cashier'],
            ];

            foreach ($staffMembers as $member) {
                $staffUser = $member['user'];
                $roleName = $member['role'];

                $staffUser->assignRole($roleName);

                $eventOrganizer->members()->syncWithoutDetaching([
                    $staffUser->id => ['event_id' => $event->id]
                ]);

                $staffUser->events()->syncWithoutDetaching($event->id);
            }
        });
    }
}
