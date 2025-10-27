<?php

namespace App\Filament\Pages;

use App\Models\Project;
use App\Models\ProjectBom;
use App\Services\BomComparisonService;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Collection;

class BomCostAnalysis extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'BOM Cost Analysis';
    protected static ?string $navigationGroup = 'Advanced Electronics';
    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.bom-cost-analysis';

    public ?int $project_id = null;
    public array $costTrendData = [];
    public array $componentUsageData = [];
    public array $optimizationSuggestions = [];
    public bool $showAnalysis = false;

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('project_id')
                ->label('Select Project')
                ->options(Project::query()->pluck('name', 'id'))
                ->searchable()
                ->reactive()
                ->afterStateUpdated(fn () => $this->analyzeProject()),
        ];
    }

    public function analyzeProject(): void
    {
        if (!$this->project_id) {
            $this->showAnalysis = false;
            return;
        }

        $project = Project::with(['boms.items.component'])->find($this->project_id);
        
        if (!$project || $project->boms->isEmpty()) {
            $this->showAnalysis = false;
            return;
        }

        // Update costs from inventory before analysis
        foreach ($project->boms as $bom) {
            $bom->updateAllItemCosts();
        }

        $this->generateCostAnalysis($project);
        $this->showAnalysis = true;
    }

    protected function generateCostAnalysis(Project $project): void
    {
        $boms = $project->boms;
        
        // Cost trend analysis
        $this->costTrendData = $this->analyzeCostTrends($boms);
        
        // Component usage and cost analysis
        $this->componentUsageData = $this->analyzeComponentUsage($boms);
        
        // Cost optimization suggestions
        $this->optimizationSuggestions = $this->generateOptimizationSuggestions($boms);
    }

    protected function analyzeCostTrends(Collection $boms): array
    {
        $trends = [];
        
        foreach ($boms as $bom) {
            $costSummary = $bom->cost_status_summary;
            $costAnalysis = $bom->cost_analysis;
            
            $trends[] = [
                'bom_id' => $bom->id,
                'total_estimated' => $bom->total_estimated_cost ?? 0,
                'total_actual' => $bom->total_actual_cost ?? 0,
                'variance' => $bom->cost_variance ?? 0,
                'variance_percentage' => $bom->cost_variance_percentage ?? 0,
                'completion_percentage' => $costSummary['completion_percentage'],
                'items_count' => $costSummary['total'],
                'missing_costs' => $costSummary['missing_costs'],
                'updated_at' => $bom->costs_calculated_at?->format('Y-m-d H:i'),
            ];
        }
        
        return $trends;
    }

    protected function analyzeComponentUsage(Collection $boms): array
    {
        $usage = [];
        $componentTotals = [];
        
        foreach ($boms as $bom) {
            foreach ($bom->items as $item) {
                if (!$item->component) continue;
                
                $componentKey = $item->component->sku;
                
                if (!isset($componentTotals[$componentKey])) {
                    $componentTotals[$componentKey] = [
                        'component' => $item->component,
                        'total_quantity' => 0,
                        'total_cost' => 0,
                        'usage_count' => 0,
                        'projects' => [],
                    ];
                }
                
                $componentTotals[$componentKey]['total_quantity'] += $item->quantity;
                $componentTotals[$componentKey]['total_cost'] += $item->total_actual_cost ?? 0;
                $componentTotals[$componentKey]['usage_count']++;
                $componentTotals[$componentKey]['projects'][] = $bom->project->name;
            }
        }
        
        // Sort by total cost descending
        uasort($componentTotals, fn($a, $b) => $b['total_cost'] <=> $a['total_cost']);
        
        return array_slice($componentTotals, 0, 20); // Top 20 components
    }

    protected function generateOptimizationSuggestions(Collection $boms): array
    {
        $suggestions = [];
        
        foreach ($boms as $bom) {
            $costAnalysis = $bom->cost_analysis;
            $costSummary = $bom->cost_status_summary;
            
            // High cost items
            if ($costAnalysis['highest_cost_item'] && $costAnalysis['highest_cost_item']['cost'] > 10) {
                $suggestions[] = [
                    'type' => 'high_cost_item',
                    'priority' => 'high',
                    'message' => "Component {$costAnalysis['highest_cost_item']['reference']} has high cost (€{$costAnalysis['highest_cost_item']['cost']}). Consider alternative suppliers.",
                    'bom_id' => $bom->id,
                ];
            }
            
            // Missing costs
            if ($costSummary['missing_costs'] > 0) {
                $suggestions[] = [
                    'type' => 'missing_costs',
                    'priority' => 'medium',
                    'message' => "{$costSummary['missing_costs']} components missing cost data. Update inventory prices for accurate analysis.",
                    'bom_id' => $bom->id,
                ];
            }
            
            // High variance
            if (abs($bom->cost_variance_percentage ?? 0) > 20) {
                $type = $bom->cost_variance_percentage > 0 ? 'over_budget' : 'under_estimated';
                $suggestions[] = [
                    'type' => $type,
                    'priority' => 'high',
                    'message' => "Cost variance of {$bom->cost_variance_percentage}% detected. Review cost estimates.",
                    'bom_id' => $bom->id,
                ];
            }
            
            // Outdated costs
            if ($costSummary['outdated_costs'] > 0) {
                $suggestions[] = [
                    'type' => 'outdated_costs',
                    'priority' => 'low',
                    'message' => "{$costSummary['outdated_costs']} components have outdated cost data. Run cost update.",
                    'bom_id' => $bom->id,
                ];
            }
        }
        
        return $suggestions;
    }
}
