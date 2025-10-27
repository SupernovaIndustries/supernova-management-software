<?php

namespace App\Filament\Widgets;

use App\Models\BillingAnalysis;
use App\Services\BillingAnalysisService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProfitLossWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $billingService = app(BillingAnalysisService::class);

        // Get or create current month analysis
        $currentMonth = now()->startOfMonth();
        $analysis = BillingAnalysis::where('analysis_type', 'monthly')
            ->where('period_start', $currentMonth)
            ->first();

        if (!$analysis) {
            // Generate on-the-fly
            $analysis = $billingService->generateMonthlyAnalysis(
                now()->year,
                now()->month
            );
        }

        return [
            Stat::make('Fatturato Mese', '€' . number_format($analysis->total_revenue, 2, ',', '.'))
                ->description('Fatturato del mese corrente')
                ->descriptionIcon('heroicon-o-currency-euro')
                ->color('success')
                ->chart($this->getRevenueSparkline()),

            Stat::make('Costi Mese', '€' . number_format($analysis->total_costs, 2, ',', '.'))
                ->description('Costi sostenuti')
                ->descriptionIcon('heroicon-o-arrow-trending-down')
                ->color('danger')
                ->chart($this->getCostsSparkline()),

            Stat::make('Profitto Netto', '€' . number_format($analysis->net_profit, 2, ',', '.'))
                ->description($analysis->profit_margin . '% margine')
                ->descriptionIcon($analysis->net_profit > 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down')
                ->color($analysis->net_profit > 0 ? 'success' : 'danger')
                ->chart($this->getProfitSparkline()),

            Stat::make('Incassato', '€' . number_format($analysis->total_paid, 2, ',', '.'))
                ->description('€' . number_format($analysis->total_outstanding, 2, ',', '.') . ' da incassare')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('info'),
        ];
    }

    protected function getRevenueSparkline(): array
    {
        $billingService = app(BillingAnalysisService::class);
        $trendData = $billingService->getMonthlyTrend(6);

        return array_column($trendData, 'invoiced');
    }

    protected function getCostsSparkline(): array
    {
        $billingService = app(BillingAnalysisService::class);
        $trendData = $billingService->getMonthlyTrend(6);

        return array_column($trendData, 'costs');
    }

    protected function getProfitSparkline(): array
    {
        $billingService = app(BillingAnalysisService::class);
        $trendData = $billingService->getMonthlyTrend(6);

        return array_column($trendData, 'profit');
    }
}
