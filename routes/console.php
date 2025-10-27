<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Supernova Management - Controllo Scadenze Automatiche
Schedule::command('supernova:check-project-deadlines')
    ->dailyAt('09:00')
    ->description('Controlla scadenze progetti e invia notifiche');

Schedule::command('supernova:check-milestone-deadlines')
    ->dailyAt('09:30')
    ->description('Controlla scadenze milestone e invia notifiche');

// Controllo aggiuntivo pomeridiano per progetti urgenti
Schedule::command('supernova:check-project-deadlines')
    ->dailyAt('14:00')
    ->description('Controllo pomeridiano scadenze progetti');

// Controllo notturno per progetti in scadenza il giorno dopo
Schedule::command('supernova:check-project-deadlines')
    ->dailyAt('20:00')
    ->description('Controllo serale scadenze progetti');

// Billing Analysis - Analisi Finanziarie Automatiche
use App\Services\BillingAnalysisService;

// Genera analisi mensile il 1° di ogni mese alle 02:00
Schedule::call(function () {
    $service = app(BillingAnalysisService::class);
    $service->generateMonthlyAnalysis(now()->year, now()->month);
})->monthlyOn(1, '02:00')
    ->description('Genera analisi billing mensile');

// Aggiorna analisi del mese corrente ogni giorno alle 01:00
Schedule::call(function () {
    $service = app(BillingAnalysisService::class);
    $service->generateMonthlyAnalysis(now()->year, now()->month);
})->dailyAt('01:00')
    ->description('Aggiorna analisi billing mese corrente');

// Genera analisi trimestrale il 1° del primo mese di ogni trimestre
Schedule::call(function () {
    $service = app(BillingAnalysisService::class);
    $quarter = ceil(now()->month / 3);
    $service->generateQuarterlyAnalysis(now()->year, $quarter);
})->monthlyOn(1, '03:00')
    ->when(function () {
        // Esegui solo se siamo nel primo mese del trimestre (1, 4, 7, 10)
        return in_array(now()->month, [1, 4, 7, 10]);
    })
    ->description('Genera analisi billing trimestrale');

// Genera analisi annuale il 1° gennaio alle 04:00
Schedule::call(function () {
    $service = app(BillingAnalysisService::class);
    $service->generateYearlyAnalysis(now()->year - 1);
})->yearlyOn(1, 1, '04:00')
    ->description('Genera analisi billing annuale anno precedente');

// Datasheet Auto-Population - Populate missing datasheet URLs for components
use App\Jobs\PopulateDatasheetUrlsJob;

// Run twice daily (9 AM and 6 PM) to populate datasheets from supplier APIs
Schedule::job(new PopulateDatasheetUrlsJob(50))
    ->dailyAt('09:00')
    ->timezone('Europe/Rome')
    ->description('Populate missing datasheet URLs (morning run)');

Schedule::job(new PopulateDatasheetUrlsJob(50))
    ->dailyAt('18:00')
    ->timezone('Europe/Rome')
    ->description('Populate missing datasheet URLs (evening run)');