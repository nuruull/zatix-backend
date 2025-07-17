<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Models\Event;
use App\Models\Rundown;
use App\Policies\EventPolicy;
use App\Models\EventOrganizer;
use App\Policies\RundownPolicy;
use App\Models\FinancialTransaction;
use App\Policies\EventOrganizerPolicy;
use App\Policies\FinancialTransactionPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Rundown::class => RundownPolicy::class,
        FinancialTransaction::class => FinancialTransactionPolicy::class,
        EventOrganizer::class => EventOrganizerPolicy::class,
        Event::class => EventPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
