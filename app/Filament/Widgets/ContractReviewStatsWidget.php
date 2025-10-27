<?php

namespace App\Filament\Widgets;

use App\Models\CustomerContract;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ContractReviewStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // Total contracts reviewed
        $reviewedCount = CustomerContract::whereNotNull('ai_reviewed_at')->count();
        $totalContracts = CustomerContract::count();
        $reviewedPercentage = $totalContracts > 0 ? round(($reviewedCount / $totalContracts) * 100) : 0;

        // Average review score
        $averageScore = CustomerContract::whereNotNull('ai_review_score')->avg('ai_review_score');
        $averageScore = $averageScore ? round($averageScore, 1) : 0;

        // Contracts with high risks (score < 60)
        $highRiskCount = CustomerContract::where('ai_review_score', '<', 60)
            ->whereNotNull('ai_review_score')
            ->count();

        // Contracts needing review (not reviewed or score < 70)
        $needsReviewCount = CustomerContract::where(function ($query) {
            $query->whereNull('ai_reviewed_at')
                ->orWhere('ai_review_score', '<', 70);
        })->count();

        // Total issues identified
        $totalIssues = CustomerContract::whereNotNull('ai_review_issues_count')
            ->sum('ai_review_issues_count');

        // Recent reviews (last 7 days)
        $recentReviews = CustomerContract::where('ai_reviewed_at', '>=', now()->subDays(7))
            ->count();

        return [
            Stat::make('Contratti Revisionati', $reviewedCount . ' / ' . $totalContracts)
                ->description("{$reviewedPercentage}% dei contratti totali")
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('info')
                ->chart($this->getReviewTrendChart()),

            Stat::make('Score Medio', $averageScore . '/100')
                ->description($this->getScoreDescription($averageScore))
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($this->getScoreColor($averageScore)),

            Stat::make('Contratti Alto Rischio', $highRiskCount)
                ->description('Score inferiore a 60')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),

            Stat::make('Richiedono Revisione', $needsReviewCount)
                ->description('Non revisionati o score < 70')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color('warning'),

            Stat::make('Problemi Totali', $totalIssues)
                ->description('Identificati dall\'AI')
                ->descriptionIcon('heroicon-m-flag')
                ->color('danger'),

            Stat::make('Revisioni Recenti', $recentReviews)
                ->description('Ultimi 7 giorni')
                ->descriptionIcon('heroicon-m-clock')
                ->color('success'),
        ];
    }

    protected function getScoreColor(float $score): string
    {
        return match (true) {
            $score >= 80 => 'success',
            $score >= 60 => 'warning',
            $score > 0 => 'danger',
            default => 'gray',
        };
    }

    protected function getScoreDescription(float $score): string
    {
        return match (true) {
            $score >= 80 => 'Qualità eccellente',
            $score >= 60 => 'Necessita miglioramenti',
            $score > 0 => 'Attenzione richiesta',
            default => 'Nessun contratto revisionato',
        };
    }

    protected function getReviewTrendChart(): array
    {
        // Get review counts for the last 7 days
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $count = CustomerContract::whereDate('ai_reviewed_at', $date)->count();
            $data[] = $count;
        }
        return $data;
    }
}
