<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

// Import Models
use App\Models\Customer;
use App\Models\CustomerContract;
use App\Models\Project;
use App\Models\InvoiceIssued;
use App\Models\InvoiceReceived;
use App\Models\ProjectComponentAllocation;

// Import Observers
use App\Observers\CustomerObserver;
use App\Observers\CustomerContractObserver;
use App\Observers\ProjectObserver;
use App\Observers\InvoiceIssuedObserver;
use App\Observers\InvoiceReceivedObserver;
use App\Observers\ProjectComponentAllocationObserver;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * The model observers for your application.
     *
     * @var array
     */
    protected $observers = [
        Customer::class => [CustomerObserver::class],
        CustomerContract::class => [CustomerContractObserver::class],
        Project::class => [ProjectObserver::class],
        InvoiceIssued::class => [InvoiceIssuedObserver::class],
        InvoiceReceived::class => [InvoiceReceivedObserver::class],
        ProjectComponentAllocation::class => [ProjectComponentAllocationObserver::class],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
