<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register model observers
        \App\Models\Customer::observe(\App\Observers\CustomerObserver::class);
        \App\Models\Project::observe(\App\Observers\ProjectObserver::class);
        \App\Models\Quotation::observe(\App\Observers\QuotationObserver::class);
        \App\Models\QuotationItem::observe(\App\Observers\QuotationItemObserver::class);
        \App\Models\InvoiceIssued::observe(\App\Observers\InvoiceIssuedObserver::class);
        \App\Models\InvoiceReceived::observe(\App\Observers\InvoiceReceivedObserver::class);
        \App\Models\CustomerContract::observe(\App\Observers\CustomerContractObserver::class);
        \App\Models\ProjectComponentAllocation::observe(\App\Observers\ProjectComponentAllocationObserver::class);
        \App\Models\ProjectBomItem::observe(\App\Observers\ProjectBomItemObserver::class);
        \App\Models\ProjectBom::observe(\App\Observers\ProjectBomObserver::class);
        \App\Models\BoardAssemblyLog::observe(\App\Observers\BoardAssemblyLogObserver::class);

        // Document upload observers - automatic Nextcloud sync with local deletion
        \App\Models\Document::observe(\App\Observers\DocumentObserver::class);
        \App\Models\ProjectDocument::observe(\App\Observers\ProjectDocumentObserver::class);
    }
}