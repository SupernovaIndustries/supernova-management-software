<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Project;
use App\Models\ProjectBom;
use App\Models\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryMovementService
{
    /**
     * Create automatic inventory movements when loading BOM for a project.
     */
    public function createMovementsFromBom(Project $project, ProjectBom $bom): array
    {
        $movements = [];
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($bom->items as $bomItem) {
                $component = Component::find($bomItem['component_id']);
                
                if (!$component) {
                    $errors[] = "Component not found: {$bomItem['component_id']}";
                    continue;
                }

                $requiredQuantity = $bomItem['quantity'] * $project->boards_produced;
                
                if ($requiredQuantity <= 0) {
                    continue;
                }

                // Check if we have enough stock
                if ($component->stock_quantity < $requiredQuantity) {
                    $errors[] = "Insufficient stock for {$component->name}. Required: {$requiredQuantity}, Available: {$component->stock_quantity}";
                    continue;
                }

                $previousQuantity = $component->stock_quantity;
                $newQuantity = $previousQuantity - $requiredQuantity;

                // Create movement record
                $movement = InventoryMovement::create([
                    'component_id' => $component->id,
                    'type' => 'out',
                    'quantity' => $requiredQuantity,
                    'quantity_before' => $previousQuantity,
                    'quantity_after' => $newQuantity,
                    'unit_cost' => $component->unit_price,
                    'reference_type' => 'project_bom',
                    'reference_id' => $project->id,
                    'reason' => "Utilizzo progetto {$project->name}",
                    'notes' => "Auto-generated for project {$project->code} - BOM: {$bom->name}",
                    'user_id' => auth()->id(),
                ]);

                // Update component stock
                $component->decrement('stock_quantity', $requiredQuantity);

                $movements[] = $movement;

                Log::info("Created inventory movement", [
                    'project' => $project->code,
                    'component' => $component->name,
                    'quantity' => $requiredQuantity,
                    'movement_id' => $movement->id,
                ]);
            }

            DB::commit();

            return [
                'success' => true,
                'movements' => $movements,
                'errors' => $errors,
                'message' => count($movements) . ' inventory movements created successfully.',
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error("Failed to create inventory movements", [
                'project' => $project->code,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'movements' => [],
                'errors' => [$e->getMessage()],
                'message' => 'Failed to create inventory movements: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Return components to inventory (reverse operation).
     */
    public function returnComponentsToInventory(Project $project, array $components): array
    {
        $movements = [];
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($components as $componentData) {
                $component = Component::find($componentData['component_id']);
                $quantity = $componentData['quantity'];

                if (!$component) {
                    $errors[] = "Component not found: {$componentData['component_id']}";
                    continue;
                }

                if ($quantity <= 0) {
                    continue;
                }

                $previousQuantity = $component->stock_quantity;
                $newQuantity = $previousQuantity + $quantity;

                // Create return movement
                $movement = InventoryMovement::create([
                    'component_id' => $component->id,
                    'type' => 'return',
                    'quantity' => $quantity,
                    'quantity_before' => $previousQuantity,
                    'quantity_after' => $newQuantity,
                    'unit_cost' => $component->unit_price,
                    'reference_type' => 'project_return',
                    'reference_id' => $project->id,
                    'reason' => "Reso progetto {$project->name}",
                    'notes' => "Returned from project {$project->code}",
                    'user_id' => auth()->id(),
                ]);

                // Update component stock
                $component->increment('stock_quantity', $quantity);

                $movements[] = $movement;
            }

            DB::commit();

            return [
                'success' => true,
                'movements' => $movements,
                'errors' => $errors,
                'message' => count($movements) . ' components returned to inventory.',
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'movements' => [],
                'errors' => [$e->getMessage()],
                'message' => 'Failed to return components: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get movements for a specific project.
     */
    public function getProjectMovements(Project $project)
    {
        return InventoryMovement::where('reference_type', 'like', 'project%')
            ->where('reference_id', $project->id)
            ->with(['component', 'user'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Calculate total cost of components used in project.
     */
    public function calculateProjectComponentCost(Project $project): float
    {
        return InventoryMovement::where('reference_type', 'like', 'project%')
            ->where('reference_id', $project->id)
            ->where('type', 'out')
            ->get()
            ->sum(function($movement) {
                return $movement->quantity * $movement->unit_cost;
            });
    }

    /**
     * Validate BOM before creating movements.
     */
    public function validateBomForMovements(ProjectBom $bom): array
    {
        $errors = [];
        $warnings = [];

        foreach ($bom->items as $bomItem) {
            $component = Component::find($bomItem['component_id']);
            
            if (!$component) {
                $errors[] = "Component not found: {$bomItem['component_id']}";
                continue;
            }

            $requiredQuantity = $bomItem['quantity'];
            
            if ($component->stock_quantity < $requiredQuantity) {
                $warnings[] = "Low stock for {$component->name}. Required: {$requiredQuantity}, Available: {$component->stock_quantity}";
            }

            if (isset($component->min_stock_level) && $component->min_stock_level > 0 &&
                ($component->stock_quantity - $requiredQuantity) < $component->min_stock_level) {
                $warnings[] = "Using {$component->name} will drop below minimum stock level ({$component->min_stock_level})";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
}