<?php

namespace App\Filament\Widgets;

use App\Services\BillingAnalysisService;
use Filament\Widgets\ChartWidget;

class MonthlyRevenueWidget extends ChartWidget
{
    protected static ?string $heading = 'Fatturato Mensile';
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'sm' => 'full',
        'md' => 2,
        'lg' => 2,
        'xl' => 2,
    ];

    protected function getData(): array
    {
        $billingService = app(BillingAnalysisService::class);
        $trendData = $billingService->getMonthlyTrend(12);

        $labels = [];
        $invoicedData = [];
        $paidData = [];

        foreach ($trendData as $item) {
            $labels[] = $item['month'];
            $invoicedData[] = $item['invoiced'];
            $paidData[] = $item['paid'];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Fatturato',
                    'data' => $invoicedData,
                    'borderColor' => 'rgb(0, 191, 191)',
                    'backgroundColor' => 'rgba(0, 191, 191, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Incassato',
                    'data' => $paidData,
                    'borderColor' => 'rgb(72, 209, 204)',
                    'backgroundColor' => 'rgba(72, 209, 204, 0.1)',
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
