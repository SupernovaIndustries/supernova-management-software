<?php

namespace App\Filament\Widgets;

use App\Models\ComponentLifecycleStatus;
use App\Models\ComponentCertification;
use Filament\Widgets\Widget;

class ComponentRiskMatrixWidget extends Widget
{
    protected static string $view = 'filament.widgets.component-risk-matrix-widget';
    
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 4;

    public function getRiskMatrix(): array
    {
        // Get components grouped by lifecycle risk and certification risk
        $components = ComponentLifecycleStatus::with(['component.certifications'])
            ->get()
            ->map(function ($status) {
                $component = $status->component;
                
                // Calculate lifecycle risk level
                $lifecycleRisk = $this->getLifecycleRiskLevel($status);
                
                // Calculate certification risk level
                $certificationRisk = $this->getCertificationRiskLevel($component);
                
                return [
                    'id' => $component->id,
                    'name' => $component->name,
                    'manufacturer' => $component->manufacturer,
                    'lifecycle_risk' => $lifecycleRisk,
                    'certification_risk' => $certificationRisk,
                    'overall_risk' => $this->calculateOverallRisk($lifecycleRisk, $certificationRisk),
                    'lifecycle_stage' => $status->lifecycle_stage,
                    'days_until_eol' => $status->days_until_eol,
                ];
            });

        // Group components by risk matrix position
        $matrix = [
            'low_low' => $components->where('lifecycle_risk', 'low')->where('certification_risk', 'low'),
            'low_medium' => $components->where('lifecycle_risk', 'low')->where('certification_risk', 'medium'),
            'low_high' => $components->where('lifecycle_risk', 'low')->where('certification_risk', 'high'),
            'medium_low' => $components->where('lifecycle_risk', 'medium')->where('certification_risk', 'low'),
            'medium_medium' => $components->where('lifecycle_risk', 'medium')->where('certification_risk', 'medium'),
            'medium_high' => $components->where('lifecycle_risk', 'medium')->where('certification_risk', 'high'),
            'high_low' => $components->where('lifecycle_risk', 'high')->where('certification_risk', 'low'),
            'high_medium' => $components->where('lifecycle_risk', 'high')->where('certification_risk', 'medium'),
            'high_high' => $components->where('lifecycle_risk', 'high')->where('certification_risk', 'high'),
        ];

        return $matrix;
    }

    private function getLifecycleRiskLevel($status): string
    {
        return match($status->lifecycle_stage) {
            'active' => 'low',
            'nrnd' => 'medium',
            'eol_announced' => $status->days_until_eol && $status->days_until_eol < 180 ? 'high' : 'medium',
            'eol', 'obsolete' => 'high',
            default => 'low',
        };
    }

    private function getCertificationRiskLevel($component): string
    {
        $validCerts = $component->certifications()->valid()->count();
        $requiredCerts = count(['CE', 'EMC', 'LVD', 'RoHS', 'REACH']);
        
        $coverage = $requiredCerts > 0 ? ($validCerts / $requiredCerts) : 0;
        
        if ($coverage >= 0.8) return 'low';
        if ($coverage >= 0.5) return 'medium';
        return 'high';
    }

    private function calculateOverallRisk(string $lifecycleRisk, string $certificationRisk): string
    {
        $riskLevels = ['low' => 1, 'medium' => 2, 'high' => 3];
        
        $avgRisk = ($riskLevels[$lifecycleRisk] + $riskLevels[$certificationRisk]) / 2;
        
        if ($avgRisk <= 1.5) return 'low';
        if ($avgRisk <= 2.5) return 'medium';
        return 'high';
    }

    public function getRiskCounts(): array
    {
        $matrix = $this->getRiskMatrix();
        
        return [
            'low' => $matrix['low_low']->count(),
            'medium' => $matrix['low_medium']->count() + $matrix['medium_low']->count() + $matrix['medium_medium']->count(),
            'high' => $matrix['low_high']->count() + $matrix['medium_high']->count() + 
                     $matrix['high_low']->count() + $matrix['high_medium']->count() + $matrix['high_high']->count(),
        ];
    }
}