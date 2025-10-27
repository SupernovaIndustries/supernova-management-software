# BOM Automatic Component Allocation System

## Overview

This system automatically allocates components from warehouse inventory to projects when BOMs (Bill of Materials) are loaded or processed. It integrates with the existing `ComponentAllocationService` and provides both automatic and manual allocation capabilities.

## Architecture

### Core Components

1. **BomAllocationService** (`app/Services/BomAllocationService.php`)
   - Centralized service for all BOM allocation operations
   - Handles allocation, deallocation, and status tracking
   - Integrates with `ComponentAllocationService` for inventory management

2. **ProjectBomItemObserver** (`app/Observers/ProjectBomItemObserver.php`)
   - Automatically triggers allocation when BOM items are created
   - Handles component updates and deletions
   - Non-blocking: failures are logged but don't prevent BOM item creation

3. **ProjectBomsRelationManager** (`app/Filament/Resources/ProjectResource/RelationManagers/ProjectBomsRelationManager.php`)
   - Filament UI for managing BOMs within projects
   - Provides manual allocation actions
   - Displays allocation status and statistics

### Database Schema

**project_component_allocations**
- `project_id` - Project receiving the components
- `component_id` - Component being allocated
- `project_bom_item_id` - Links allocation to specific BOM item
- `quantity_allocated` - Total quantity allocated
- `quantity_used` - Quantity marked as used
- `quantity_remaining` - Quantity still available
- `status` - allocated, in_use, completed, returned
- `unit_cost` - Cost per unit
- `total_cost` - Total allocation cost

**project_bom_items**
- `allocated` - Boolean flag indicating allocation status
- `component_id` - Linked component (null if not matched)
- `actual_unit_cost` - Cost from inventory
- `total_actual_cost` - Total cost for this item

## Features

### 1. Automatic Allocation on BOM Item Creation

When a `ProjectBomItem` is created with a `component_id`:

1. Observer detects the creation
2. Retrieves project's `total_boards_ordered` for quantity calculation
3. Calculates total quantity: `item_quantity * boards_count`
4. Checks stock availability
5. Creates `ProjectComponentAllocation` record
6. Creates `InventoryMovement` (type: 'out')
7. Updates component stock
8. Updates project's `total_components_cost`
9. Marks BOM item as `allocated = true`

**Error Handling:**
- Insufficient stock: Logged as warning, item remains unallocated
- Component not found: Logged as warning
- Other errors: Logged as error, don't block creation

### 2. Manual Allocation via Filament UI

#### Via Project BOM Tab

1. Navigate to Project > Edit > "Bill of Materials (BOM)" tab
2. Select a BOM
3. Click "Allocate Components" action
4. Specify number of boards to produce (defaults to project's `total_boards_ordered`)
5. System processes all unallocated items

**Results Displayed:**
- Number of items allocated
- Items already allocated (skipped)
- Items with insufficient stock (with details)
- Items without components assigned
- Any errors encountered

#### Actions Available

- **Allocate Components**: Allocate all unallocated items in BOM
- **Deallocate**: Return all allocated components to inventory
- **Update Costs**: Refresh costs from current inventory prices
- **Import BOM from CSV**: Import BOM file from Syncthing

### 3. Component Quantity Calculation

Total quantity = `BOM item quantity` × `Number of boards`

Example:
- BOM item: R1, quantity = 4 (4 resistors per board)
- Project: total_boards_ordered = 10
- **Total allocated**: 40 resistors

### 4. Allocation Validation

Before allocating, the system checks:

1. **Not Already Allocated**: Prevents duplicate allocations
2. **Component Assigned**: BOM item must have valid `component_id`
3. **Stock Availability**: Sufficient quantity in warehouse
4. **No Existing Project Allocation**: Checks for existing allocation to same project/component/BOM item

### 5. Status Tracking

**BOM Status:**
- `pending` - No allocation yet
- `partially_allocated` - Some items allocated
- `allocated` - All items allocated
- `processing` - Import/processing in progress
- `completed` - BOM fully processed and allocated

**Allocation Status:**
- `allocated` - Components reserved for project
- `in_use` - Components being used in production
- `completed` - All allocated components used
- `returned` - Components returned to warehouse

## Usage Examples

### Example 1: Automatic Allocation on Import

```php
use App\Services\BomService;

$bomService = app(BomService::class);

// Import BOM from CSV - items will auto-allocate if components exist
$bom = $bomService->importProjectBom($project, 'path/to/bom.csv');

// Check allocation results
$summary = app(BomAllocationService::class)->getAllocationSummary($bom);
// Returns: total_items, allocated_items, allocation_percentage, etc.
```

### Example 2: Manual Allocation via Service

```php
use App\Services\BomAllocationService;

$allocationService = app(BomAllocationService::class);

// Allocate entire BOM (uses project's total_boards_ordered)
$results = $allocationService->allocateBom($bom);

// Or specify custom boards count
$results = $allocationService->allocateBom($bom, boardsCount: 50);

// Check results
if ($results['allocated'] > 0) {
    // Success
    logger()->info("Allocated {$results['allocated']} items");
}

// Handle insufficient stock
foreach ($results['insufficient_stock_items'] as $item) {
    logger()->warning("Insufficient stock for {$item['component']}: need {$item['required']}, have {$item['available']}");
}
```

### Example 3: Allocate Single BOM Item

```php
use App\Services\BomAllocationService;

$allocationService = app(BomAllocationService::class);
$bomItem = ProjectBomItem::find($id);

// Allocate with specific board count
$result = $allocationService->allocateBomItem($bomItem, boardsCount: 10);

if ($result['success']) {
    logger()->info($result['message']);
} else {
    logger()->error($result['message']);
}
```

### Example 4: Deallocate BOM

```php
use App\Services\BomAllocationService;

$allocationService = app(BomAllocationService::class);

// Return all components to inventory
$results = $allocationService->deallocateBom($bom);

logger()->info("Deallocated {$results['deallocated']} items");
```

## Testing Guide

### 1. Test Automatic Allocation

**Setup:**
```sql
-- Ensure you have a project with boards ordered
UPDATE projects SET total_boards_ordered = 5 WHERE id = 1;

-- Ensure you have components with stock
UPDATE components SET stock_quantity = 100 WHERE id = 1;
```

**Test:**
1. Import a BOM or create BOM items with component_id assigned
2. Check logs: `tail -f storage/logs/laravel.log | grep "Auto-allocated"`
3. Verify allocation created: `SELECT * FROM project_component_allocations WHERE project_id = 1;`
4. Verify stock decreased: `SELECT stock_quantity FROM components WHERE id = 1;`
5. Verify BOM item marked allocated: `SELECT allocated FROM project_bom_items WHERE id = X;`

### 2. Test Manual Allocation via UI

1. Go to Admin Panel > Projects > Edit Project
2. Click "Bill of Materials (BOM)" tab
3. Find a BOM with unallocated items
4. Click "Allocate Components" action
5. Enter number of boards (e.g., 10)
6. Click "Allocate"
7. Verify notification shows results
8. Verify BOM status changed to "allocated" or "partially_allocated"

### 3. Test Insufficient Stock Handling

**Setup:**
```sql
-- Set component stock to insufficient level
UPDATE components SET stock_quantity = 1 WHERE id = 1;
```

**Test:**
1. Try to allocate BOM item requiring quantity > 1
2. Verify warning notification appears
3. Verify item remains unallocated
4. Check logs for insufficient stock warning

### 4. Test Deallocation

1. Find an allocated BOM
2. Click "Deallocate" action
3. Confirm action
4. Verify success notification
5. Check that:
   - Components returned to inventory
   - BOM items marked as unallocated
   - BOM status changed to "pending"
   - Inventory movements created (type: 'return')

### 5. Test Cost Updates

1. Find allocated BOM
2. Update component prices in inventory
3. Click "Update Costs" action
4. Verify costs refreshed from current inventory prices
5. Check `project_bom_items.actual_unit_cost` updated

### 6. Test Edge Cases

**No Component Assigned:**
```php
// Create BOM item without component
$bomItem = ProjectBomItem::create([
    'project_bom_id' => $bomId,
    'reference' => 'R1',
    'quantity' => 1,
    // No component_id
]);
// Should not trigger allocation, but should not fail
```

**Component Removed:**
```php
// Update existing allocated item to remove component
$bomItem->update(['component_id' => null]);
// Should trigger deallocation
```

**Duplicate Allocation Prevention:**
```php
// Try to allocate same item twice
$service->allocateBomItem($bomItem, 10);
$service->allocateBomItem($bomItem, 10); // Should skip, already allocated
```

## Logging

The system logs important events:

**Info Level:**
- `Auto-allocated BOM item on creation`
- `Auto-allocated BOM item after component update`
- `Deallocated BOM item before deletion`
- `BOM allocation completed`
- `BOM deallocation completed`

**Warning Level:**
- `Auto-allocation failed for BOM item` (insufficient stock, no component)

**Error Level:**
- `Exception during auto-allocation of BOM item`
- `Failed to allocate BOM item on component update`
- `Failed to deallocate BOM item before deletion`

**Log Location:** `storage/logs/laravel.log`

**Search Examples:**
```bash
# View all allocation activity
tail -f storage/logs/laravel.log | grep -i "allocated"

# View errors only
tail -f storage/logs/laravel.log | grep "ERROR.*BOM"

# View insufficient stock warnings
tail -f storage/logs/laravel.log | grep "Insufficient stock"
```

## Troubleshooting

### Issue: Components not auto-allocating

**Possible Causes:**
1. BOM items created without `component_id`
2. Insufficient stock in warehouse
3. Observer not registered

**Solutions:**
```bash
# Check if observer is registered
grep "ProjectBomItem::observe" app/Providers/AppServiceProvider.php

# Check logs for warnings
grep "Auto-allocation failed" storage/logs/laravel.log

# Manually trigger allocation
php artisan tinker
$bom = ProjectBom::find($id);
app(\App\Services\BomAllocationService::class)->allocateBom($bom);
```

### Issue: Stock not decreasing

**Check:**
```sql
-- Verify inventory movement created
SELECT * FROM inventory_movements
WHERE component_id = X
AND type = 'out'
ORDER BY created_at DESC;

-- Verify component stock updated
SELECT stock_quantity FROM components WHERE id = X;

-- Verify allocation created
SELECT * FROM project_component_allocations
WHERE component_id = X
ORDER BY created_at DESC;
```

### Issue: Allocation fails with transaction error

**Cause:** Database transaction rollback due to constraint violation

**Solutions:**
- Ensure all foreign keys exist (project_id, component_id)
- Check database constraints
- Review error logs for specific constraint violation

## API Reference

### BomAllocationService

**allocateBomItem(ProjectBomItem $bomItem, int $boardsCount = 1): array**
- Allocates a single BOM item
- Returns: `['success' => bool, 'message' => string, ...]`

**allocateBom(ProjectBom $bom, ?int $boardsCount = null): array**
- Allocates all items in a BOM
- Returns: `['total_items', 'allocated', 'insufficient_stock', ...]`

**deallocateBomItem(ProjectBomItem $bomItem): array**
- Returns components to warehouse
- Returns: `['success' => bool, 'message' => string]`

**deallocateBom(ProjectBom $bom): array**
- Deallocates all items in a BOM
- Returns: `['total_items', 'deallocated', ...]`

**getAllocationSummary(ProjectBom $bom): array**
- Gets allocation statistics
- Returns: `['total_items', 'allocated_items', 'allocation_percentage', ...]`

## Migration Notes

### From Old System

The old allocation methods in `ProjectBomItem` are now deprecated but still functional:

```php
// Old way (still works)
$bomItem->allocateComponent($component);
$bomItem->deallocateComponent();

// New way (recommended)
app(BomAllocationService::class)->allocateBomItem($bomItem, $boardsCount);
app(BomAllocationService::class)->deallocateBomItem($bomItem);
```

### Database Migration Required

No migration required - the `project_bom_item_id` field already exists in `project_component_allocations` table.

## Performance Considerations

- Allocation processes each BOM item individually in a transaction
- Large BOMs (>100 items) may take several seconds
- Failed allocations don't block subsequent items
- Consider using queues for very large BOMs (future enhancement)

## Future Enhancements

1. **Queue Support**: Process large BOM allocations asynchronously
2. **Partial Allocation**: Allow allocating available quantity when insufficient
3. **Allocation Templates**: Save and reuse allocation strategies
4. **Notification System**: Email alerts for allocation failures
5. **Batch Allocation**: Allocate multiple BOMs at once
6. **Cost Optimization**: Suggest cheaper component alternatives
