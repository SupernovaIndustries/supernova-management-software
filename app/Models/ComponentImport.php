<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class ComponentImport extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'job_id',
        'supplier',
        'original_filename',
        'file_type',
        'components_imported',
        'components_updated',
        'components_skipped',
        'components_failed',
        'movements_created',
        'invoice_number',
        'invoice_path',
        'invoice_date',
        'invoice_total',
        'destination_project_id',
        'status',
        'error_message',
        'started_at',
        'completed_at',
        'field_mapping',
        'import_details',
        'notes',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'invoice_total' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'field_mapping' => 'array',
        'import_details' => 'array',
    ];

    /**
     * Get the user who performed the import
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the destination project for this import
     */
    public function destinationProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'destination_project_id');
    }

    /**
     * Get all components created/updated by this import
     */
    public function components(): HasMany
    {
        return $this->hasMany(Component::class, 'import_id');
    }

    /**
     * Get all inventory movements created by this import
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'import_id');
    }

    /**
     * Delete the import and all related data
     * This will:
     * - Delete all inventory movements (cascade)
     * - Revert stock quantities for affected components
     * - Set import_id to null for components (set null)
     * - Delete the import record
     */
    public function deleteWithRelatedData(): bool
    {
        try {
            Log::info('Starting import deletion with related data', [
                'import_id' => $this->id,
                'job_id' => $this->job_id,
                'supplier' => $this->supplier
            ]);

            // Get all inventory movements for this import before deletion
            $movements = $this->inventoryMovements()->get();

            // Revert stock quantities for each component
            foreach ($movements as $movement) {
                $component = $movement->component;
                if ($component) {
                    $currentStock = $component->stock_quantity ?? 0;
                    $revertedStock = max(0, $currentStock - $movement->quantity);

                    $component->update(['stock_quantity' => $revertedStock]);

                    Log::info('Reverted stock for component', [
                        'component_id' => $component->id,
                        'component_sku' => $component->sku,
                        'movement_quantity' => $movement->quantity,
                        'stock_before' => $currentStock,
                        'stock_after' => $revertedStock
                    ]);
                }
            }

            // Delete inventory movements (cascade will handle this)
            $movementsDeleted = $movements->count();
            $this->inventoryMovements()->delete();

            // Get components count before setting import_id to null
            $componentsAffected = $this->components()->count();

            // Set import_id to null for all components (they remain in the system)
            $this->components()->update(['import_id' => null]);

            // Delete the import record
            $this->delete();

            Log::info('Import deleted successfully with related data', [
                'import_id' => $this->id,
                'movements_deleted' => $movementsDeleted,
                'components_affected' => $componentsAffected
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete import with related data', [
                'import_id' => $this->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Get the total duration of the import in seconds
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        return $this->completed_at->diffInSeconds($this->started_at);
    }

    /**
     * Get human readable duration
     */
    public function getHumanDurationAttribute(): ?string
    {
        if (!$this->duration) {
            return null;
        }

        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;

        if ($minutes > 0) {
            return "{$minutes}m {$seconds}s";
        }

        return "{$seconds}s";
    }

    /**
     * Get success rate percentage
     */
    public function getSuccessRateAttribute(): float
    {
        $total = $this->components_imported + $this->components_updated + $this->components_failed;

        if ($total === 0) {
            return 0;
        }

        return round((($this->components_imported + $this->components_updated) / $total) * 100, 2);
    }

    /**
     * Check if import is completed successfully
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if import failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if import is still processing
     */
    public function isProcessing(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }
}
