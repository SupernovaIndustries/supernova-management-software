<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidAllocationException;
use App\Models\Component;
use App\Models\Customer;
use App\Models\InvoiceReceived;
use App\Models\InvoiceComponentMapping;
use App\Models\InventoryMovement;
use App\Models\Project;
use App\Models\ProjectComponentAllocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ComponentAllocationService
{
    /**
     * Allocate components to a project
     * Creates allocation record + inventory movement (OUT)
     */
    public function allocateToProject(
        Project $project,
        Component $component,
        float $quantity,
        ?InvoiceReceived $sourceInvoice = null
    ): ProjectComponentAllocation {
        return DB::transaction(function () use ($project, $component, $quantity, $sourceInvoice) {
            // 1. Check if enough stock available
            if ($component->stock_quantity < $quantity) {
                throw new InsufficientStockException(
                    "Component {$component->sku} ({$component->name}) has insufficient stock. " .
                    "Available: {$component->stock_quantity}, Requested: {$quantity}"
                );
            }

            // 2. Determine unit cost
            $unitCost = $sourceInvoice
                ? $this->calculateUnitCostFromInvoice($sourceInvoice, $component)
                : $component->unit_price ?? 0;

            $totalCost = $quantity * $unitCost;

            // 3. Create ProjectComponentAllocation record
            $allocation = ProjectComponentAllocation::create([
                'project_id' => $project->id,
                'component_id' => $component->id,
                'quantity_allocated' => $quantity,
                'quantity_used' => 0,
                'quantity_remaining' => $quantity,
                'status' => 'allocated',
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'source_invoice_id' => $sourceInvoice ? $sourceInvoice->id : null,
                'allocated_at' => now(),
            ]);

            // 4. Create InventoryMovement record (type='out')
            $movement = InventoryMovement::create([
                'component_id' => $component->id,
                'type' => 'out',
                'quantity' => -$quantity, // Negative for outgoing
                'quantity_before' => $component->stock_quantity,
                'quantity_after' => $component->stock_quantity - $quantity,
                'reference_type' => 'project',
                'reference_id' => $project->id,
                'unit_cost' => $unitCost,
                'reason' => 'Allocated to project',
                'notes' => "Allocated to project: {$project->name}",
                'user_id' => auth()->id(),
            ]);

            // 5. Update component stock
            $component->decrement('stock_quantity', $quantity);

            // 6. Update project total_components_cost
            $project->increment('total_components_cost', $totalCost);

            // 7. Update project's _components_used.json on Nextcloud (TODO)
            try {
                $this->updateProjectComponentsJson($project);
            } catch (\Exception $e) {
                Log::error('Failed to update project components JSON: ' . $e->getMessage());
            }

            return $allocation;
        });
    }

    /**
     * Mark components as used in production
     */
    public function markAsUsed(ProjectComponentAllocation $allocation, float $quantityUsed): void
    {
        DB::transaction(function () use ($allocation, $quantityUsed) {
            // Validate quantity
            if ($quantityUsed > $allocation->quantity_remaining) {
                throw new InvalidAllocationException(
                    "Cannot mark {$quantityUsed} as used. Only {$allocation->quantity_remaining} remaining."
                );
            }

            // Update allocation
            $allocation->quantity_used += $quantityUsed;
            $allocation->quantity_remaining -= $quantityUsed;

            if ($allocation->quantity_remaining <= 0) {
                $allocation->status = 'completed';
                $allocation->completed_at = now();
            } else {
                $allocation->status = 'in_use';
            }

            $allocation->save();

            // Update project's _components_used.json
            try {
                $this->updateProjectComponentsJson($allocation->project);
            } catch (\Exception $e) {
                Log::error('Failed to update project components JSON: ' . $e->getMessage());
            }
        });
    }

    /**
     * Return unused components to warehouse
     */
    public function returnToWarehouse(ProjectComponentAllocation $allocation, float $quantityReturned): void
    {
        DB::transaction(function () use ($allocation, $quantityReturned) {
            // 1. Check quantity_remaining >= quantityReturned
            if ($quantityReturned > $allocation->quantity_remaining) {
                throw new InvalidAllocationException(
                    "Cannot return {$quantityReturned}. Only {$allocation->quantity_remaining} remaining in allocation."
                );
            }

            $component = $allocation->component;

            // 2. Create InventoryMovement (type='in'): Reverse the allocation
            $movement = InventoryMovement::create([
                'component_id' => $component->id,
                'type' => 'return',
                'quantity' => $quantityReturned, // Positive for incoming
                'quantity_before' => $component->stock_quantity,
                'quantity_after' => $component->stock_quantity + $quantityReturned,
                'reference_type' => 'project',
                'reference_id' => $allocation->project_id,
                'unit_cost' => $allocation->unit_cost,
                'reason' => 'Returned from project',
                'notes' => "Returned from project allocation #{$allocation->id}",
                'user_id' => auth()->id(),
            ]);

            // 3. Update component stock
            $component->increment('stock_quantity', $quantityReturned);

            // 4. Update allocation
            $returnedCost = $quantityReturned * $allocation->unit_cost;
            $allocation->quantity_allocated -= $quantityReturned;
            $allocation->quantity_remaining -= $quantityReturned;
            $allocation->total_cost -= $returnedCost;

            if ($allocation->quantity_allocated <= 0) {
                $allocation->status = 'returned';
            }

            $allocation->save();

            // 5. Update project costs
            $allocation->project->decrement('total_components_cost', $returnedCost);

            // 6. Update JSON
            try {
                $this->updateProjectComponentsJson($allocation->project);
            } catch (\Exception $e) {
                Log::error('Failed to update project components JSON: ' . $e->getMessage());
            }
        });
    }

    /**
     * Get all components allocated to a project
     */
    public function getProjectAllocations(Project $project): Collection
    {
        return ProjectComponentAllocation::where('project_id', $project->id)
            ->with(['component', 'sourceInvoice'])
            ->orderBy('allocated_at', 'desc')
            ->get();
    }

    /**
     * Get component usage by customer
     */
    public function getComponentUsageByCustomer(Customer $customer, ?int $year = null): array
    {
        $query = $customer->projects();

        if ($year) {
            $query->whereYear('created_at', $year);
        }

        $projects = $query->get();
        $projectIds = $projects->pluck('id');

        $allocations = ProjectComponentAllocation::whereIn('project_id', $projectIds)
            ->with(['component', 'project'])
            ->get();

        $totalCost = $allocations->sum('total_cost');

        $components = $allocations->groupBy('component_id')->map(function ($componentAllocations) {
            $component = $componentAllocations->first()->component;
            $projects = $componentAllocations->groupBy('project_id')->map(function ($projectAllocations) {
                return [
                    'project_name' => $projectAllocations->first()->project->name,
                    'quantity' => $projectAllocations->sum('quantity_allocated'),
                    'cost' => $projectAllocations->sum('total_cost'),
                ];
            })->values();

            return [
                'component' => [
                    'id' => $component->id,
                    'name' => $component->name,
                    'sku' => $component->sku,
                    'manufacturer_part_number' => $component->manufacturer_part_number,
                ],
                'total_quantity' => $componentAllocations->sum('quantity_allocated'),
                'total_cost' => $componentAllocations->sum('total_cost'),
                'projects' => $projects,
            ];
        })->values();

        return [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->company_name,
            ],
            'year' => $year,
            'total_cost' => $totalCost,
            'components' => $components,
            'projects_count' => $projects->count(),
        ];
    }

    /**
     * Link invoice components to actual components
     * When receiving an invoice for components, link them
     */
    public function linkInvoiceToComponents(
        InvoiceReceived $invoice,
        array $componentMappings
    ): void {
        DB::transaction(function () use ($invoice, $componentMappings) {
            foreach ($componentMappings as $mapping) {
                $component = Component::findOrFail($mapping['component_id']);
                $quantity = $mapping['quantity'];
                $unitPrice = $mapping['unit_price'];
                $totalCost = $quantity * $unitPrice;

                // 1. Create InvoiceComponentMapping record
                $componentMapping = InvoiceComponentMapping::create([
                    'invoice_received_id' => $invoice->id,
                    'invoice_received_item_id' => $mapping['invoice_item_id'] ?? null,
                    'component_id' => $component->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_cost' => $totalCost,
                ]);

                // 2. Create InventoryMovement (type='in')
                $movement = InventoryMovement::create([
                    'component_id' => $component->id,
                    'type' => 'in',
                    'quantity' => $quantity,
                    'quantity_before' => $component->stock_quantity,
                    'quantity_after' => $component->stock_quantity + $quantity,
                    'reference_type' => 'invoice',
                    'reference_id' => $invoice->id,
                    'unit_cost' => $unitPrice,
                    'reason' => 'Purchase',
                    'notes' => "Invoice: {$invoice->invoice_number}",
                    'user_id' => auth()->id(),
                    'invoice_number' => $invoice->invoice_number,
                    'supplier' => $invoice->supplier_name,
                ]);

                // Link movement to mapping
                $componentMapping->update(['inventory_movement_id' => $movement->id]);

                // 3. Update component stock
                $component->increment('stock_quantity', $quantity);

                // 4. Update component average_cost (weighted average)
                $this->updateComponentAverageCost($component, $quantity, $unitPrice);
            }

            // 5. Generate {INVOICE}_components.json for Nextcloud (TODO)
            try {
                $this->generateInvoiceComponentsJson($invoice);
            } catch (\Exception $e) {
                Log::error('Failed to generate invoice components JSON: ' . $e->getMessage());
            }
        });
    }

    /**
     * Calculate warehouse value (total stock value)
     */
    public function calculateWarehouseValue(): array
    {
        $components = Component::where('stock_quantity', '>', 0)->get();

        $totalValue = $components->sum(function ($component) {
            return $component->stock_quantity * ($component->unit_price ?? 0);
        });

        $byCategory = $components->groupBy('category_id')->map(function ($categoryComponents) {
            $category = $categoryComponents->first()->category;
            $value = $categoryComponents->sum(function ($component) {
                return $component->stock_quantity * ($component->unit_price ?? 0);
            });

            return [
                'category_name' => $category ? $category->name : 'Uncategorized',
                'total_value' => $value,
                'component_count' => $categoryComponents->count(),
            ];
        })->values();

        // Slow moving: components with no movement in last 6 months
        $sixMonthsAgo = now()->subMonths(6);
        $slowMoving = Component::whereDoesntHave('inventoryMovements', function ($query) use ($sixMonthsAgo) {
            $query->where('created_at', '>=', $sixMonthsAgo);
        })
            ->where('stock_quantity', '>', 0)
            ->select('id', 'name', 'sku', 'stock_quantity', 'unit_price')
            ->get()
            ->map(function ($component) {
                return [
                    'id' => $component->id,
                    'name' => $component->name,
                    'sku' => $component->sku,
                    'stock' => $component->stock_quantity,
                    'value' => $component->stock_quantity * ($component->unit_price ?? 0),
                ];
            });

        // Out of stock
        $outOfStock = Component::where('stock_quantity', '<=', 0)
            ->select('id', 'name', 'sku', 'min_stock_level')
            ->get()
            ->map(function ($component) {
                return [
                    'id' => $component->id,
                    'name' => $component->name,
                    'sku' => $component->sku,
                    'min_level' => $component->min_stock_level,
                ];
            });

        return [
            'total_value' => $totalValue,
            'by_category' => $byCategory,
            'slow_moving' => $slowMoving,
            'out_of_stock' => $outOfStock,
            'total_components' => $components->count(),
        ];
    }

    /**
     * Generate components usage report (for Analytics)
     */
    public function generateUsageReport(int $year): array
    {
        $startDate = now()->setYear($year)->startOfYear();
        $endDate = now()->setYear($year)->endOfYear();

        $allocations = ProjectComponentAllocation::whereBetween('allocated_at', [$startDate, $endDate])
            ->with(['project', 'project.customer', 'component'])
            ->get();

        $byCustomer = $allocations->groupBy('project.customer_id')->map(function ($customerAllocations) {
            $customer = $customerAllocations->first()->project->customer;
            return [
                'customer_id' => $customer->id,
                'customer_name' => $customer->company_name,
                'total_cost' => $customerAllocations->sum('total_cost'),
                'total_quantity' => $customerAllocations->sum('quantity_allocated'),
                'projects_count' => $customerAllocations->pluck('project_id')->unique()->count(),
            ];
        })->values();

        $byProject = $allocations->groupBy('project_id')->map(function ($projectAllocations) {
            $project = $projectAllocations->first()->project;
            return [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'customer_name' => $project->customer->company_name,
                'total_cost' => $projectAllocations->sum('total_cost'),
                'components_count' => $projectAllocations->pluck('component_id')->unique()->count(),
            ];
        })->values();

        $byComponent = $allocations->groupBy('component_id')->map(function ($componentAllocations) {
            $component = $componentAllocations->first()->component;
            return [
                'component_id' => $component->id,
                'component_name' => $component->name,
                'sku' => $component->sku,
                'total_quantity' => $componentAllocations->sum('quantity_allocated'),
                'total_cost' => $componentAllocations->sum('total_cost'),
                'projects_count' => $componentAllocations->pluck('project_id')->unique()->count(),
            ];
        })->values();

        return [
            'year' => $year,
            'total_allocations' => $allocations->count(),
            'total_cost' => $allocations->sum('total_cost'),
            'by_customer' => $byCustomer,
            'by_project' => $byProject,
            'by_component' => $byComponent,
        ];
    }

    /**
     * Calculate unit cost from invoice
     */
    protected function calculateUnitCostFromInvoice(InvoiceReceived $invoice, Component $component): float
    {
        // Try to find the specific cost from invoice items
        $item = $invoice->items()
            ->where('component_id', $component->id)
            ->first();

        if ($item) {
            return $item->unit_price;
        }

        // Fallback to component's unit price
        return $component->unit_price ?? 0;
    }

    /**
     * Update component average cost (weighted average)
     */
    protected function updateComponentAverageCost(Component $component, float $newQuantity, float $newUnitPrice): void
    {
        $currentStock = $component->stock_quantity - $newQuantity; // Stock before this purchase
        $currentCost = $component->unit_price ?? 0;

        if ($currentStock <= 0) {
            // If no stock before, just use new price
            $component->update(['unit_price' => $newUnitPrice]);
        } else {
            // Weighted average
            $totalValue = ($currentStock * $currentCost) + ($newQuantity * $newUnitPrice);
            $totalQuantity = $currentStock + $newQuantity;
            $averageCost = $totalQuantity > 0 ? $totalValue / $totalQuantity : $newUnitPrice;

            $component->update(['unit_price' => round($averageCost, 4)]);
        }
    }

    /**
     * Update project components JSON file
     */
    protected function updateProjectComponentsJson(Project $project): void
    {
        $allocations = $this->getProjectAllocations($project);

        $data = [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'total_cost' => $project->total_components_cost,
            'updated_at' => now()->toIso8601String(),
            'allocations' => $allocations->map(function ($allocation) {
                return [
                    'component_id' => $allocation->component_id,
                    'component_name' => $allocation->component->name,
                    'sku' => $allocation->component->sku,
                    'quantity_allocated' => (float) $allocation->quantity_allocated,
                    'quantity_used' => (float) $allocation->quantity_used,
                    'quantity_remaining' => (float) $allocation->quantity_remaining,
                    'unit_cost' => (float) $allocation->unit_cost,
                    'total_cost' => (float) $allocation->total_cost,
                    'status' => $allocation->status,
                    'allocated_at' => $allocation->allocated_at->toIso8601String(),
                ];
            }),
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // TODO: Upload to Nextcloud
        Log::info("Project components JSON generated for project {$project->id}", ['size' => strlen($json)]);
    }

    /**
     * Generate invoice components JSON
     */
    protected function generateInvoiceComponentsJson(InvoiceReceived $invoice): void
    {
        $mappings = $invoice->componentMappings()->with('component')->get();

        $data = [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'supplier_name' => $invoice->supplier_name,
            'issue_date' => $invoice->issue_date->format('Y-m-d'),
            'total' => (float) $invoice->total,
            'components' => $mappings->map(function ($mapping) {
                return [
                    'component_id' => $mapping->component_id,
                    'component_name' => $mapping->component->name,
                    'sku' => $mapping->component->sku,
                    'quantity' => (float) $mapping->quantity,
                    'unit_price' => (float) $mapping->unit_price,
                    'total_cost' => (float) $mapping->total_cost,
                ];
            }),
            'generated_at' => now()->toIso8601String(),
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // TODO: Upload to Nextcloud Magazzino/Fatture_Magazzino/...
        Log::info("Invoice components JSON generated for invoice {$invoice->invoice_number}", ['size' => strlen($json)]);
    }
}
