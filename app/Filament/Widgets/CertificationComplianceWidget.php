<?php

namespace App\Filament\Widgets;

use App\Models\ComponentCertification;
use App\Services\CertificationManagementService;
use Filament\Widgets\ChartWidget;

class CertificationComplianceWidget extends ChartWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'CE Certification Coverage';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $service = app(CertificationManagementService::class);
        $stats = $service->getCertificationStatistics();
        
        $labels = [];
        $data = [];
        $colors = ['#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#F97316'];
        
        foreach ($stats['certification_coverage'] as $type => $coverage) {
            $labels[] = $coverage['name'];
            $data[] = round($coverage['coverage_percentage'], 1);
        }
        
        return [
            'datasets' => [
                [
                    'label' => 'Coverage %',
                    'data' => $data,
                    'backgroundColor' => array_slice($colors, 0, count($data)),
                    'borderColor' => array_slice($colors, 0, count($data)),
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}