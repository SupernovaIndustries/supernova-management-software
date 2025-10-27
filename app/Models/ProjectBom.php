<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectBom extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'file_path',
        'folder_path',
        'nextcloud_path',
        'uploaded_file_path',
        'components_data',
        'status',
        'processed_at',
        'processed_by',
        'total_estimated_cost',
        'total_actual_cost',
        'cost_variance',
        'cost_variance_percentage',
        'costs_calculated_at',
    ];

    protected $casts = [
        'components_data' => 'array',
        'processed_at' => 'datetime',
        'total_estimated_cost' => 'decimal:2',
        'total_actual_cost' => 'decimal:2',
        'cost_variance' => 'decimal:2',
        'cost_variance_percentage' => 'decimal:2',
        'costs_calculated_at' => 'datetime',
    ];

    /**
     * Get the project this BOM belongs to.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user who processed this BOM.
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Get all BOM items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProjectBomItem::class);
    }

    /**
     * Get allocated items count.
     */
    public function getAllocatedItemsCountAttribute(): int
    {
        return $this->items()->where('allocated', true)->count();
    }

    /**
     * Get total items count.
     */
    public function getTotalItemsCountAttribute(): int
    {
        return $this->items()->count();
    }

    /**
     * Get allocation percentage.
     */
    public function getAllocationPercentageAttribute(): float
    {
        if ($this->total_items_count === 0) {
            return 0;
        }

        return round(($this->allocated_items_count / $this->total_items_count) * 100, 2);
    }

    /**
     * Check if BOM is fully allocated.
     */
    public function isFullyAllocated(): bool
    {
        return $this->allocated_items_count === $this->total_items_count;
    }

    /**
     * Calculate and update total costs from all BOM items.
     */
    public function calculateTotalCosts(): void
    {
        $totalEstimated = $this->items()->sum('total_estimated_cost') ?? 0;
        $totalActual = $this->items()->sum('total_actual_cost') ?? 0;
        
        $variance = $totalActual - $totalEstimated;
        $variancePercentage = $totalEstimated > 0 ? ($variance / $totalEstimated) * 100 : 0;

        $this->update([
            'total_estimated_cost' => $totalEstimated,
            'total_actual_cost' => $totalActual,
            'cost_variance' => $variance,
            'cost_variance_percentage' => round($variancePercentage, 2),
            'costs_calculated_at' => now(),
        ]);
    }

    /**
     * Update all item costs from inventory.
     */
    public function updateAllItemCosts(): void
    {
        foreach ($this->items()->whereHas('component')->get() as $item) {
            $item->updateActualCosts();
        }

        $this->calculateTotalCosts();
    }

    /**
     * Get cost completion percentage.
     */
    public function getCostCompletionPercentageAttribute(): float
    {
        $totalItems = $this->items()->count();
        if ($totalItems === 0) {
            return 0;
        }

        $itemsWithCosts = $this->items()->whereNotNull('actual_unit_cost')->count();
        return round(($itemsWithCosts / $totalItems) * 100, 2);
    }

    /**
     * Get items with missing costs.
     */
    public function itemsWithMissingCosts()
    {
        return $this->items()->whereNull('actual_unit_cost');
    }

    /**
     * Get items with outdated costs.
     */
    public function itemsWithOutdatedCosts()
    {
        return $this->items()->whereHas('component', function ($query) {
            $query->whereColumn('components.updated_at', '>', 'project_bom_items.cost_updated_at');
        });
    }

    /**
     * Get cost status summary.
     */
    public function getCostStatusSummaryAttribute(): array
    {
        $items = $this->items;
        $total = $items->count();
        
        if ($total === 0) {
            return [
                'total' => 0,
                'with_costs' => 0,
                'missing_costs' => 0,
                'outdated_costs' => 0,
                'completion_percentage' => 0,
            ];
        }

        $withCosts = $items->whereNotNull('actual_unit_cost')->count();
        $missingCosts = $items->whereNull('actual_unit_cost')->count();
        $outdatedCosts = $items->filter(fn($item) => !$item->areCostsUpToDate())->count();

        return [
            'total' => $total,
            'with_costs' => $withCosts,
            'missing_costs' => $missingCosts,
            'outdated_costs' => $outdatedCosts,
            'completion_percentage' => round(($withCosts / $total) * 100, 2),
        ];
    }

    /**
     * Get cost analysis data.
     */
    public function getCostAnalysisAttribute(): array
    {
        $items = $this->items()->whereNotNull('total_actual_cost')->get();
        
        if ($items->isEmpty()) {
            return [
                'total_cost' => 0,
                'average_cost_per_item' => 0,
                'highest_cost_item' => null,
                'cost_by_category' => [],
            ];
        }

        $totalCost = $items->sum('total_actual_cost');
        $averageCost = $totalCost / $items->count();
        $highestCostItem = $items->sortByDesc('total_actual_cost')->first();

        // Group by component category if available
        $costByCategory = $items->filter(fn($item) => $item->component)
            ->groupBy(fn($item) => $item->component->category->name ?? 'Unknown')
            ->map(fn($group) => $group->sum('total_actual_cost'))
            ->sortDesc();

        return [
            'total_cost' => round($totalCost, 2),
            'average_cost_per_item' => round($averageCost, 2),
            'highest_cost_item' => $highestCostItem ? [
                'reference' => $highestCostItem->reference,
                'cost' => $highestCostItem->total_actual_cost,
                'component' => $highestCostItem->component?->name,
            ] : null,
            'cost_by_category' => $costByCategory->toArray(),
        ];
    }
}