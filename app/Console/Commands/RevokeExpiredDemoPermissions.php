<?php

namespace App\Console\Commands;

use App\Models\DemoRequest;
use Illuminate\Console\Command;

class RevokeExpiredDemoPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:revoke-expired-demo-permissions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = now();

        $expired = DemoRequest::whereNotNull('demo_access_expiry')
            ->whereDate('demo_access_expiry', '<', $now)
            ->get();

        foreach ($expired as $demo) {
            $user = $demo->user;
            if ($user) {
                $permissions = [
                    'event.create',
                    'event.edit',
                    'event.delete',
                    'facility.create',
                    'facility.edit',
                    'facility.delete',
                    'ticket.store',
                    'ticket.update',
                    'ticket.destroy',
                ];
                $user->revokePermissionTo($permissions);
            }
        }

        $this->info("Expired demo permissions revoked.");
    }
}
