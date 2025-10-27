<?php

namespace App\Filament\Widgets;

use App\Models\PaymentMilestone;
use App\Services\BillingAnalysisService;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;

class RevenueForecastWidget extends ChartWidget
{
    protected static ?string $heading = 'Previsione Fatturato';
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'sm' => 'full',
        'md' => 2,
        'lg' => 2,
        'xl' => 1,
    ];

    protected function getData(): array
    {
        $billingService = app(BillingAnalysisService::class);

        $labels = [];
        $historicalData = [];
        $forecastData = [];

        // Get last 6 months historical data
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $labels[] = $date->format('M Y');

            $trendData = $billingService->getMonthlyTrend(6);
            $historicalData[] = $trendData[$i]['invoiced'] ?? 0;
            $forecastData[] = null;
        }

        // Get next 6 months forecast based on pending milestones
        for ($i = 1; $i <= 6; $i++) {
            $date = now()->addMonths($i);
            $labels[] = $date->format('M Y');

            $start = $date->copy()->startOfMonth();
            $end = $date->copy()->endOfMonth();

            $forecast = PaymentMilestone::where('status', 'pending')
                ->whereBetween('expected_date', [$start, $end])
                ->sum('amount');

            $historicalData[] = null;
            $forecastData[] = (float)$forecast;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Storico',
                    'data' => $historicalData,
                    'borderColor' => 'rgb(0, 191, 191)',
                    'backgroundColor' => 'rgba(0, 191, 191, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Previsione',
                    'data' => $forecastData,
                    'borderColor' => 'rgb(64, 224, 208)',
                    'backgroundColor' => 'rgba(64, 224, 208, 0.1)',
                    'borderDash' => [5, 5],
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => true,
            'responsive' => true,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => 'function(value) { return "€" + value.toLocaleString(); }',
                    ],
                ],
                'x' => [
                    'ticks' => [
                        'maxRotation' => 45,
                        'minRotation' => 45,
                    ],
                ],
            ],
        ];
    }
}
