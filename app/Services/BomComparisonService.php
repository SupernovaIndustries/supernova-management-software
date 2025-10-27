<?php

namespace App\Services;

use App\Models\ProjectBom;
use App\Models\ProjectBomItem;
use App\Models\Component;
use Illuminate\Support\Collection;

class BomComparisonService
{
    /**
     * Compare two BOMs and return differences
     */
    public function compareBoms(ProjectBom $bom1, ProjectBom $bom2): array
    {
        $items1 = $this->getBomItemsMap($bom1);
        $items2 = $this->getBomItemsMap($bom2);

        $comparison = [
            'added' => [],      // Items in bom2 but not in bom1
            'removed' => [],    // Items in bom1 but not in bom2
            'modified' => [],   // Items that exist in both but with different quantities/properties
            'unchanged' => [],  // Items that are identical in both BOMs
            'cost_impact' => [
                'bom1_total' => $this->calculateBomCost($bom1),
                'bom2_total' => $this->calculateBomCost($bom2),
                'difference' => 0,
                'percentage_change' => 0,
            ],
        ];

        // Find added items (in bom2 but not in bom1)
        foreach ($items2 as $designator => $item2) {
            if (!isset($items1[$designator])) {
                $comparison['added'][] = [
                    'designator' => $designator,
                    'component' => $item2['component'],
                    'quantity' => $item2['quantity'],
                    'cost_impact' => $item2['quantity'] * $item2['component']->unit_price,
                ];
            }
        }

        // Find removed and modified items
        foreach ($items1 as $designator => $item1) {
            if (!isset($items2[$designator])) {
                // Item removed
                $comparison['removed'][] = [
                    'designator' => $designator,
                    'component' => $item1['component'],
                    'quantity' => $item1['quantity'],
                    'cost_impact' => -($item1['quantity'] * $item1['component']->unit_price),
                ];
            } else {
                // Item exists in both, check for changes
                $item2 = $items2[$designator];
                
                if ($item1['component_id'] !== $item2['component_id'] || 
                    $item1['quantity'] !== $item2['quantity']) {
                    
                    $oldCost = $item1['quantity'] * $item1['component']->unit_price;
                    $newCost = $item2['quantity'] * $item2['component']->unit_price;
                    
                    $comparison['modified'][] = [
                        'designator' => $designator,
                        'old' => [
                            'component' => $item1['component'],
                            'quantity' => $item1['quantity'],
                            'cost' => $oldCost,
                        ],
                        'new' => [
                            'component' => $item2['component'],
                            'quantity' => $item2['quantity'],
                            'cost' => $newCost,
                        ],
                        'cost_impact' => $newCost - $oldCost,
                        'changes' => $this->identifyChanges($item1, $item2),
                    ];
                } else {
                    // Item unchanged
                    $comparison['unchanged'][] = [
                        'designator' => $designator,
                        'component' => $item1['component'],
                        'quantity' => $item1['quantity'],
                    ];
                }
            }
        }

        // Calculate cost impact
        $bom1Total = $comparison['cost_impact']['bom1_total'];
        $bom2Total = $comparison['cost_impact']['bom2_total'];
        $comparison['cost_impact']['difference'] = $bom2Total - $bom1Total;
        
        if ($bom1Total > 0) {
            $comparison['cost_impact']['percentage_change'] = 
                (($bom2Total - $bom1Total) / $bom1Total) * 100;
        }

        return $comparison;
    }

    /**
     * Get BOM items as a map (designator => item data)
     */
    private function getBomItemsMap(ProjectBom $bom): array
    {
        $items = [];
        
        foreach ($bom->items()->with('component')->get() as $item) {
            $items[$item->designator] = [
                'component_id' => $item->component_id,
                'component' => $item->component,
                'quantity' => $item->quantity,
                'notes' => $item->notes,
            ];
        }

        return $items;
    }

    /**
     * Identify specific changes between two BOM items
     */
    private function identifyChanges(array $item1, array $item2): array
    {
        $changes = [];

        if ($item1['component_id'] !== $item2['component_id']) {
            $changes[] = 'component_changed';
        }

        if ($item1['quantity'] !== $item2['quantity']) {
            $changes[] = 'quantity_changed';
        }

        if ($item1['notes'] !== $item2['notes']) {
            $changes[] = 'notes_changed';
        }

        return $changes;
    }

    /**
     * Calculate total cost of a BOM
     */
    public function calculateBomCost(ProjectBom $bom): float
    {
        return $bom->items()
            ->with('component')
            ->get()
            ->sum(function ($item) {
                return $item->quantity * ($item->component->unit_price ?? 0);
            });
    }

    /**
     * Analyze cost trends across multiple BOM versions
     */
    public function analyzeCostTrend(Collection $boms): array
    {
        $trend = [];
        
        foreach ($boms as $bom) {
            $trend[] = [
                'version' => $bom->version,
                'date' => $bom->created_at,
                'total_cost' => $this->calculateBomCost($bom),
                'item_count' => $bom->items()->count(),
            ];
        }

        // Calculate trend indicators
        $costs = array_column($trend, 'total_cost');
        $trend['summary'] = [
            'min_cost' => min($costs),
            'max_cost' => max($costs),
            'avg_cost' => array_sum($costs) / count($costs),
            'cost_variance' => max($costs) - min($costs),
            'trend_direction' => $this->calculateTrendDirection($costs),
        ];

        return $trend;
    }

    /**
     * Calculate trend direction (increasing, decreasing, stable)
     */
    private function calculateTrendDirection(array $costs): string
    {
        if (count($costs) < 2) {
            return 'insufficient_data';
        }

        $first = reset($costs);
        $last = end($costs);
        $difference = $last - $first;
        $threshold = $first * 0.05; // 5% threshold

        if (abs($difference) < $threshold) {
            return 'stable';
        }

        return $difference > 0 ? 'increasing' : 'decreasing';
    }

    /**
     * Find component usage across multiple projects
     */
    public function findComponentUsage(Component $component): array
    {
        $bomItems = ProjectBomItem::where('component_id', $component->id)
            ->with(['bom.project', 'component'])
            ->get();

        $usage = [
            'total_projects' => $bomItems->pluck('bom.project.id')->unique()->count(),
            'total_quantity' => $bomItems->sum('quantity'),
            'projects' => [],
            'designators' => $bomItems->pluck('designator')->unique()->values()->toArray(),
            'avg_quantity_per_project' => 0,
        ];

        // Group by project
        $projectGroups = $bomItems->groupBy('bom.project.id');
        
        foreach ($projectGroups as $projectId => $items) {
            $project = $items->first()->bom->project;
            $totalQty = $items->sum('quantity');
            
            $usage['projects'][] = [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'project_status' => $project->status,
                'quantity_used' => $totalQty,
                'designators' => $items->pluck('designator')->toArray(),
                'bom_version' => $items->first()->bom->version,
            ];
        }

        if ($usage['total_projects'] > 0) {
            $usage['avg_quantity_per_project'] = $usage['total_quantity'] / $usage['total_projects'];
        }

        return $usage;
    }

    /**
     * Generate BOM optimization suggestions
     */
    public function generateOptimizationSuggestions(ProjectBom $bom): array
    {
        $suggestions = [];
        $items = $bom->items()->with('component')->get();

        // Check for cost optimization opportunities
        foreach ($items as $item) {
            $component = $item->component;
            
            // Suggest alternatives for expensive components
            if ($component->unit_price > 10) { // Configurable threshold
                $alternatives = $component->alternatives()
                    ->where('compatibility_score', '>=', 0.85)
                    ->with('alternativeComponent')
                    ->get();

                foreach ($alternatives as $alt) {
                    if ($alt->alternativeComponent->unit_price < $component->unit_price) {
                        $savings = ($component->unit_price - $alt->alternativeComponent->unit_price) * $item->quantity;
                        
                        $suggestions[] = [
                            'type' => 'cost_optimization',
                            'designator' => $item->designator,
                            'current_component' => $component,
                            'suggested_component' => $alt->alternativeComponent,
                            'potential_savings' => $savings,
                            'compatibility_score' => $alt->compatibility_score,
                            'notes' => "Consider replacing {$component->name} with {$alt->alternativeComponent->name}",
                        ];
                    }
                }
            }

            // Check for lifecycle issues
            if ($component->lifecycleStatus && $component->lifecycleStatus->isAtRisk()) {
                $suggestions[] = [
                    'type' => 'lifecycle_risk',
                    'designator' => $item->designator,
                    'component' => $component,
                    'risk_level' => $component->lifecycleStatus->urgency_level,
                    'notes' => "Component is {$component->lifecycleStatus->lifecycle_stage}",
                ];
            }

            // Check for stock availability
            if ($component->stock_quantity < $item->quantity) {
                $suggestions[] = [
                    'type' => 'stock_shortage',
                    'designator' => $item->designator,
                    'component' => $component,
                    'required_quantity' => $item->quantity,
                    'available_stock' => $component->stock_quantity,
                    'shortage' => $item->quantity - $component->stock_quantity,
                    'notes' => "Insufficient stock for production",
                ];
            }
        }

        return $suggestions;
    }
}