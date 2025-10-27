<?php

namespace App\Observers;

use App\Models\ProjectBomItem;
use App\Services\BomAllocationService;
use Illuminate\Support\Facades\Log;

class ProjectBomItemObserver
{
    /**
     * Handle the ProjectBomItem "created" event.
     * Automatically allocate component to project when BOM item is created.
     *
     * @param ProjectBomItem $bomItem
     * @return void
     */
    public function created(ProjectBomItem $bomItem): void
    {
        // Only auto-allocate if component is assigned
        if (!$bomItem->component_id) {
            return;
        }

        // Skip if already marked as allocated
        if ($bomItem->allocated) {
            return;
        }

        try {
            // Get BOM allocation service
            $allocationService = app(BomAllocationService::class);

            // Get boards count from project (default to 1 if not set)
            $boardsCount = $bomItem->bom->project->total_boards_ordered ?? 1;

            // Attempt automatic allocation
            $result = $allocationService->allocateBomItem($bomItem, $boardsCount);

            if ($result['success']) {
                Log::info('Auto-allocated BOM item on creation', [
                    'bom_item_id' => $bomItem->id,
                    'component_id' => $bomItem->component_id,
                    'quantity' => $result['quantity'] ?? 0,
                ]);
            } else {
                // Log failures but don't throw exception - we don't want to block BOM item creation
                Log::warning('Auto-allocation failed for BOM item', [
                    'bom_item_id' => $bomItem->id,
                    'component_id' => $bomItem->component_id,
                    'reason' => $result['reason'] ?? 'unknown',
                    'message' => $result['message'] ?? 'No message',
                ]);
            }
        } catch (\Exception $e) {
            // Catch any exceptions to prevent blocking BOM item creation
            Log::error('Exception during auto-allocation of BOM item', [
                'bom_item_id' => $bomItem->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Handle the ProjectBomItem "updated" event.
     * Re-allocate if component changes and allocation is enabled.
     *
     * @param ProjectBomItem $bomItem
     * @return void
     */
    public function updated(ProjectBomItem $bomItem): void
    {
        // Check if component_id changed
        if (!$bomItem->isDirty('component_id')) {
            return;
        }

        // If component was removed, deallocate
        if ($bomItem->getOriginal('component_id') && !$bomItem->component_id) {
            if ($bomItem->allocated) {
                try {
                    $allocationService = app(BomAllocationService::class);
                    $allocationService->deallocateBomItem($bomItem);

                    Log::info('Deallocated BOM item after component removal', [
                        'bom_item_id' => $bomItem->id,
                        'old_component_id' => $bomItem->getOriginal('component_id'),
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to deallocate BOM item on component removal', [
                        'bom_item_id' => $bomItem->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            return;
        }

        // If component was added or changed, try to allocate
        if ($bomItem->component_id && !$bomItem->allocated) {
            try {
                $allocationService = app(BomAllocationService::class);
                $boardsCount = $bomItem->bom->project->total_boards_ordered ?? 1;

                $result = $allocationService->allocateBomItem($bomItem, $boardsCount);

                if ($result['success']) {
                    Log::info('Auto-allocated BOM item after component update', [
                        'bom_item_id' => $bomItem->id,
                        'component_id' => $bomItem->component_id,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to allocate BOM item on component update', [
                    'bom_item_id' => $bomItem->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle the ProjectBomItem "deleting" event.
     * Deallocate before deletion.
     *
     * @param ProjectBomItem $bomItem
     * @return void
     */
    public function deleting(ProjectBomItem $bomItem): void
    {
        // Deallocate if allocated
        if ($bomItem->allocated && $bomItem->component_id) {
            try {
                $allocationService = app(BomAllocationService::class);
                $allocationService->deallocateBomItem($bomItem);

                Log::info('Deallocated BOM item before deletion', [
                    'bom_item_id' => $bomItem->id,
                    'component_id' => $bomItem->component_id,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to deallocate BOM item before deletion', [
                    'bom_item_id' => $bomItem->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
