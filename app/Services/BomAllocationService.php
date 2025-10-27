<?php

namespace App\Services;

use App\Models\Component;
use App\Models\Project;
use App\Models\ProjectBom;
use App\Models\ProjectBomItem;
use App\Models\ProjectComponentAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for allocating components from inventory to BOM items
 */
class BomAllocationService
{
    public function __construct(
        protected ComponentAllocationService $allocationService
    ) {}

    /**
     * Automatically allocate a single BOM item to project inventory
     *
     * @param ProjectBomItem $bomItem
     * @param int $boardsCount Number of boards to produce (multiplier for quantity)
     * @return array Result with status and details
     */
    public function allocateBomItem(ProjectBomItem $bomItem, int $boardsCount = 1): array
    {
        try {
            // Skip if already allocated
            if ($bomItem->allocated) {
                return [
                    'success' => false,
                    'reason' => 'already_allocated',
                    'message' => "BOM item {$bomItem->reference} is already allocated",
                ];
            }

            // Skip if no component assigned
            if (!$bomItem->component_id) {
                return [
                    'success' => false,
                    'reason' => 'no_component',
                    'message' => "BOM item {$bomItem->reference} has no component assigned",
                ];
            }

            $component = $bomItem->component;
            $project = $bomItem->bom->project;

            // Calculate total quantity needed (quantity per board * boards count)
            $totalQuantity = $bomItem->quantity * $boardsCount;

            // Check if already allocated via ProjectComponentAllocation
            $existingAllocation = ProjectComponentAllocation::where('project_id', $project->id)
                ->where('component_id', $component->id)
                ->where('project_bom_item_id', $bomItem->id)
                ->first();

            if ($existingAllocation) {
                return [
                    'success' => false,
                    'reason' => 'already_allocated_in_project',
                    'message' => "Component {$component->name} already allocated to project for this BOM item",
                    'allocation_id' => $existingAllocation->id,
                ];
            }

            // Check stock availability
            if ($component->stock_quantity < $totalQuantity) {
                return [
                    'success' => false,
                    'reason' => 'insufficient_stock',
                    'message' => "Insufficient stock for {$component->name}. Required: {$totalQuantity}, Available: {$component->stock_quantity}",
                    'component' => $component->name,
                    'required' => $totalQuantity,
                    'available' => $component->stock_quantity,
                ];
            }

            // Perform allocation using ComponentAllocationService
            DB::transaction(function () use ($bomItem, $component, $project, $totalQuantity) {
                $allocation = $this->allocationService->allocateToProject(
                    $project,
                    $component,
                    $totalQuantity
                );

                // Link allocation to BOM item
                $allocation->update(['project_bom_item_id' => $bomItem->id]);

                // Mark BOM item as allocated
                $bomItem->update(['allocated' => true]);

                // Update costs if not already set
                if (!$bomItem->actual_unit_cost) {
                    $bomItem->updateActualCosts();
                }

                Log::info('BOM item allocated successfully', [
                    'bom_item_id' => $bomItem->id,
                    'component_id' => $component->id,
                    'project_id' => $project->id,
                    'quantity' => $totalQuantity,
                    'allocation_id' => $allocation->id,
                ]);
            });

            return [
                'success' => true,
                'message' => "Successfully allocated {$totalQuantity}x {$component->name} to {$bomItem->reference}",
                'component' => $component->name,
                'quantity' => $totalQuantity,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to allocate BOM item', [
                'bom_item_id' => $bomItem->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'reason' => 'exception',
                'message' => "Error allocating BOM item: {$e->getMessage()}",
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Allocate all items in a BOM
     *
     * @param ProjectBom $bom
     * @param int|null $boardsCount Number of boards to produce (defaults to project's total_boards_ordered)
     * @return array Summary of allocation results
     */
    public function allocateBom(ProjectBom $bom, ?int $boardsCount = null): array
    {
        // Use project's total_boards_ordered if not specified
        if ($boardsCount === null) {
            $boardsCount = $bom->project->total_boards_ordered ?? 1;
        }

        $results = [
            'total_items' => 0,
            'allocated' => 0,
            'already_allocated' => 0,
            'no_component' => 0,
            'insufficient_stock' => 0,
            'errors' => 0,
            'details' => [],
            'insufficient_stock_items' => [],
            'error_items' => [],
        ];

        foreach ($bom->items as $bomItem) {
            $results['total_items']++;

            $result = $this->allocateBomItem($bomItem, $boardsCount);

            if ($result['success']) {
                $results['allocated']++;
            } else {
                // Categorize failures
                switch ($result['reason']) {
                    case 'already_allocated':
                    case 'already_allocated_in_project':
                        $results['already_allocated']++;
                        break;
                    case 'no_component':
                        $results['no_component']++;
                        break;
                    case 'insufficient_stock':
                        $results['insufficient_stock']++;
                        $results['insufficient_stock_items'][] = [
                            'reference' => $bomItem->reference,
                            'component' => $result['component'] ?? 'Unknown',
                            'required' => $result['required'] ?? 0,
                            'available' => $result['available'] ?? 0,
                        ];
                        break;
                    default:
                        $results['errors']++;
                        $results['error_items'][] = [
                            'reference' => $bomItem->reference,
                            'error' => $result['message'] ?? 'Unknown error',
                        ];
                        break;
                }
            }

            $results['details'][] = $result;
        }

        // Update BOM status if fully allocated
        if ($results['allocated'] + $results['already_allocated'] === $results['total_items']) {
            $bom->update([
                'status' => 'allocated',
                'processed_at' => now(),
                'processed_by' => auth()->id(),
            ]);
        } elseif ($results['allocated'] > 0) {
            $bom->update([
                'status' => 'partially_allocated',
                'processed_at' => now(),
                'processed_by' => auth()->id(),
            ]);
        }

        Log::info('BOM allocation completed', [
            'bom_id' => $bom->id,
            'project_id' => $bom->project_id,
            'boards_count' => $boardsCount,
            'results' => $results,
        ]);

        return $results;
    }

    /**
     * Deallocate a BOM item (return components to inventory)
     *
     * @param ProjectBomItem $bomItem
     * @return array Result with status and details
     */
    public function deallocateBomItem(ProjectBomItem $bomItem): array
    {
        try {
            if (!$bomItem->allocated) {
                return [
                    'success' => false,
                    'reason' => 'not_allocated',
                    'message' => "BOM item {$bomItem->reference} is not allocated",
                ];
            }

            // Find the allocation
            $allocation = ProjectComponentAllocation::where('project_id', $bomItem->bom->project_id)
                ->where('project_bom_item_id', $bomItem->id)
                ->first();

            if (!$allocation) {
                // Mark as not allocated anyway
                $bomItem->update(['allocated' => false]);

                return [
                    'success' => false,
                    'reason' => 'allocation_not_found',
                    'message' => "Allocation record not found for BOM item {$bomItem->reference}",
                ];
            }

            // Return components to warehouse
            DB::transaction(function () use ($bomItem, $allocation) {
                $this->allocationService->returnToWarehouse(
                    $allocation,
                    $allocation->quantity_remaining
                );

                // Mark BOM item as not allocated
                $bomItem->update(['allocated' => false]);

                Log::info('BOM item deallocated successfully', [
                    'bom_item_id' => $bomItem->id,
                    'allocation_id' => $allocation->id,
                    'quantity_returned' => $allocation->quantity_remaining,
                ]);
            });

            return [
                'success' => true,
                'message' => "Successfully deallocated {$bomItem->reference}",
            ];

        } catch (\Exception $e) {
            Log::error('Failed to deallocate BOM item', [
                'bom_item_id' => $bomItem->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'reason' => 'exception',
                'message' => "Error deallocating BOM item: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Deallocate all items in a BOM
     *
     * @param ProjectBom $bom
     * @return array Summary of deallocation results
     */
    public function deallocateBom(ProjectBom $bom): array
    {
        $results = [
            'total_items' => 0,
            'deallocated' => 0,
            'not_allocated' => 0,
            'errors' => 0,
            'details' => [],
        ];

        foreach ($bom->items()->where('allocated', true)->get() as $bomItem) {
            $results['total_items']++;

            $result = $this->deallocateBomItem($bomItem);

            if ($result['success']) {
                $results['deallocated']++;
            } else {
                if ($result['reason'] === 'not_allocated') {
                    $results['not_allocated']++;
                } else {
                    $results['errors']++;
                }
            }

            $results['details'][] = $result;
        }

        // Update BOM status
        $bom->update([
            'status' => 'pending',
            'processed_at' => null,
            'processed_by' => null,
        ]);

        Log::info('BOM deallocation completed', [
            'bom_id' => $bom->id,
            'results' => $results,
        ]);

        return $results;
    }

    /**
     * Get allocation summary for a BOM
     *
     * @param ProjectBom $bom
     * @return array
     */
    public function getAllocationSummary(ProjectBom $bom): array
    {
        $items = $bom->items;
        $allocatedItems = $items->where('allocated', true);

        return [
            'total_items' => $items->count(),
            'allocated_items' => $allocatedItems->count(),
            'unallocated_items' => $items->where('allocated', false)->count(),
            'items_with_component' => $items->whereNotNull('component_id')->count(),
            'items_without_component' => $items->whereNull('component_id')->count(),
            'allocation_percentage' => $items->count() > 0
                ? round(($allocatedItems->count() / $items->count()) * 100, 2)
                : 0,
            'total_allocated_cost' => $allocatedItems->sum('total_actual_cost') ?? 0,
        ];
    }
}
