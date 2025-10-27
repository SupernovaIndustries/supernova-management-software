<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectBomItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_bom_id',
        'component_id',
        'reference',
        'value',
        'footprint',
        'manufacturer_part',
        'quantity',
        'allocated',
        'notes',
        'estimated_unit_cost',
        'actual_unit_cost',
        'total_estimated_cost',
        'total_actual_cost',
        'cost_source',
        'cost_updated_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'allocated' => 'boolean',
        'estimated_unit_cost' => 'decimal:4',
        'actual_unit_cost' => 'decimal:4',
        'total_estimated_cost' => 'decimal:4',
        'total_actual_cost' => 'decimal:4',
        'cost_updated_at' => 'datetime',
    ];

    /**
     * Get the BOM this item belongs to.
     */
    public function bom(): BelongsTo
    {
        return $this->belongsTo(ProjectBom::class, 'project_bom_id');
    }

    /**
     * Get the component for this item.
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    /**
     * Allocate component to this BOM item.
     *
     * @deprecated Use BomAllocationService::allocateBomItem() instead
     */
    public function allocateComponent(Component $component): void
    {
        // Use the new allocation service
        $allocationService = app(\App\Services\BomAllocationService::class);
        $boardsCount = $this->bom->project->total_boards_ordered ?? 1;

        // Only update component_id if not already set
        if (!$this->component_id) {
            $this->update(['component_id' => $component->id]);
        }

        $allocationService->allocateBomItem($this, $boardsCount);
    }

    /**
     * Deallocate component from this BOM item.
     *
     * @deprecated Use BomAllocationService::deallocateBomItem() instead
     */
    public function deallocateComponent(): void
    {
        if (!$this->component_id || !$this->allocated) {
            return;
        }

        // Use the new allocation service
        $allocationService = app(\App\Services\BomAllocationService::class);
        $allocationService->deallocateBomItem($this);
    }

    /**
     * Calculate and update actual costs from inventory.
     */
    public function updateActualCosts(): void
    {
        if (!$this->component_id) {
            return;
        }

        $component = $this->component;
        $actualUnitCost = $component->unit_price ?? 0;
        $totalActualCost = $actualUnitCost * $this->quantity;

        $this->update([
            'actual_unit_cost' => $actualUnitCost,
            'total_actual_cost' => $totalActualCost,
            'cost_source' => 'inventory',
            'cost_updated_at' => now(),
        ]);
    }

    /**
     * Calculate estimated costs (can be manual or from suppliers).
     */
    public function updateEstimatedCosts(float $unitCost, string $source = 'manual'): void
    {
        $totalEstimatedCost = $unitCost * $this->quantity;

        $this->update([
            'estimated_unit_cost' => $unitCost,
            'total_estimated_cost' => $totalEstimatedCost,
            'cost_source' => $source,
            'cost_updated_at' => now(),
        ]);
    }

    /**
     * Get cost variance (actual vs estimated).
     */
    public function getCostVarianceAttribute(): ?float
    {
        if (!$this->total_actual_cost || !$this->total_estimated_cost) {
            return null;
        }

        return $this->total_actual_cost - $this->total_estimated_cost;
    }

    /**
     * Get cost variance percentage.
     */
    public function getCostVariancePercentageAttribute(): ?float
    {
        if (!$this->total_estimated_cost || $this->total_estimated_cost == 0) {
            return null;
        }

        $variance = $this->cost_variance;
        if ($variance === null) {
            return null;
        }

        return round(($variance / $this->total_estimated_cost) * 100, 2);
    }

    /**
     * Check if costs are up to date.
     */
    public function areCostsUpToDate(): bool
    {
        if (!$this->cost_updated_at || !$this->component) {
            return false;
        }

        return $this->cost_updated_at->isAfter($this->component->updated_at);
    }

    /**
     * Get cost status indicator.
     */
    public function getCostStatusAttribute(): string
    {
        if (!$this->actual_unit_cost) {
            return 'missing';
        }

        if (!$this->areCostsUpToDate()) {
            return 'outdated';
        }

        $variance = $this->cost_variance_percentage;
        if ($variance === null) {
            return 'partial';
        }

        if (abs($variance) <= 5) {
            return 'accurate';
        } elseif (abs($variance) <= 15) {
            return 'acceptable';
        } else {
            return 'significant_variance';
        }
    }
}