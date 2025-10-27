<?php

namespace App\Observers;

use App\Models\ProjectComponentAllocation;
use App\Services\NextcloudService;
use Illuminate\Support\Facades\Log;

class ProjectComponentAllocationObserver
{
    protected NextcloudService $nextcloudService;

    public function __construct()
    {
        $this->nextcloudService = new NextcloudService();
    }

    /**
     * Handle the ProjectComponentAllocation "created" event.
     */
    public function created(ProjectComponentAllocation $allocation): void
    {
        try {
            $project = $allocation->project;

            if ($project && $project->nextcloud_folder_created) {
                // Update project's _components_used.json
                $this->nextcloudService->generateComponentsUsedJson($project);

                // Update project's _components_allocation.json in Ordini_Componenti
                $this->generateComponentsAllocationJson($allocation);

                Log::info("Components JSON updated for allocation: {$allocation->id}");
            }

            // Create inventory movement (OUT) if not already created
            // This would be handled by InventoryMovement creation typically

            // Update component stock (if applicable)
            // This might be handled elsewhere in your system

        } catch (\Exception $e) {
            Log::error("ProjectComponentAllocationObserver::created error: " . $e->getMessage());
        }
    }

    /**
     * Handle the ProjectComponentAllocation "updated" event.
     */
    public function updated(ProjectComponentAllocation $allocation): void
    {
        try {
            $project = $allocation->project;

            // If status changed to 'completed', finalize
            if ($allocation->isDirty('status') && $allocation->status === 'completed') {
                // Finalize inventory movement
                // Update project.total_components_cost
                if ($project) {
                    $totalCost = $project->componentAllocations()->sum('total_cost');
                    $project->update(['total_components_cost' => $totalCost]);

                    // Regenerate components JSON
                    $this->nextcloudService->generateComponentsUsedJson($project);

                    Log::info("Component allocation completed: {$allocation->id}");
                }
            }

            // Update JSON files if quantities or costs change
            if ($allocation->isDirty(['quantity_allocated', 'quantity_used', 'total_cost']) && $project) {
                $this->nextcloudService->generateComponentsUsedJson($project);
                $this->generateComponentsAllocationJson($allocation);
            }

        } catch (\Exception $e) {
            Log::error("ProjectComponentAllocationObserver::updated error: " . $e->getMessage());
        }
    }

    /**
     * Generate components allocation JSON in project's Ordini_Componenti folder
     */
    protected function generateComponentsAllocationJson(ProjectComponentAllocation $allocation): bool
    {
        try {
            $project = $allocation->project;
            if (!$project || !$project->nextcloud_folder_created) {
                return false;
            }

            $basePath = $this->nextcloudService->getProjectBasePath($project);
            $orderNumber = $allocation->id; // Or use a specific order number if available

            $remotePath = "{$basePath}/03_Produzione/Ordini_Componenti/ORDER_{$orderNumber}/_components_allocation.json";

            $data = [
                'allocation_id' => $allocation->id,
                'project_code' => $project->code,
                'component_code' => $allocation->component->code ?? 'N/A',
                'description' => $allocation->component->description ?? '',
                'quantity_allocated' => (float) $allocation->quantity_allocated,
                'quantity_used' => (float) $allocation->quantity_used,
                'quantity_remaining' => (float) $allocation->quantity_remaining,
                'unit_cost' => (float) $allocation->unit_cost,
                'total_cost' => (float) $allocation->total_cost,
                'status' => $allocation->status,
                'source_invoice' => $allocation->sourceInvoice->invoice_number ?? null,
                'allocated_at' => $allocation->allocated_at?->toIso8601String(),
                'completed_at' => $allocation->completed_at?->toIso8601String(),
            ];

            $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            // Ensure folder exists
            $helper = new \App\Helpers\NextcloudHelper();
            $helper->createDirectory(dirname($remotePath));

            return $helper->uploadContent($jsonContent, $remotePath);

        } catch (\Exception $e) {
            Log::error("Error generating components allocation JSON: " . $e->getMessage());
            return false;
        }
    }
}
