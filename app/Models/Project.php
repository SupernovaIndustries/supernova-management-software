<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Project extends Model
{
    use HasFactory;
    
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($project) {
            if (!$project->code) {
                $project->code = static::generateProjectCode($project->name);
            }
            if (!$project->user_id) {
                $project->user_id = Auth::id();
            }
        });
    }
    
    /**
     * Generate project code from name
     */
    protected static function generateProjectCode(string $name): string
    {
        // Usa tutto il nome del progetto, convertito in uppercase e slug
        $baseCode = Str::upper(Str::slug($name, '-'));
        
        // Controlla se esiste già
        $existingProject = static::where('code', $baseCode)->first();
        
        if (!$existingProject) {
            return $baseCode;
        }
        
        // Se esiste, aggiungi un numero progressivo
        $counter = 2;
        do {
            $codeWithNumber = $baseCode . '-' . str_pad($counter, 2, '0', STR_PAD_LEFT);
            $existingProject = static::where('code', $codeWithNumber)->first();
            $counter++;
        } while ($existingProject);
        
        return $codeWithNumber;
    }

    protected $fillable = [
        'code',
        'name',
        'description',
        'customer_id',
        'user_id',
        'status',
        'project_status',
        'priority_id',
        'progress_id',
        'start_date',
        'due_date',
        'completed_date',
        'budget',
        'manual_budget',
        'actual_cost',
        'progress',
        'milestones',
        'notes',
        'folder',
        'total_boards_ordered',
        'boards_produced',
        'boards_assembled',
        'email_notifications',
        'notification_days_before',
        'last_notification_sent',
        'client_email',
        // Nextcloud fields
        'nextcloud_folder_created',
        'nextcloud_base_path',
        'components_tracked',
        'total_components_cost',
        // AI fields
        'completion_percentage',
        'ai_priority_score',
        'ai_priority_data',
        'ai_priority_calculated_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'completed_date' => 'date',
        'budget' => 'decimal:2',
        'manual_budget' => 'boolean',
        'actual_cost' => 'decimal:2',
        'email_notifications' => 'boolean',
        'last_notification_sent' => 'datetime',
        'progress_id' => 'integer',
        'priority_id' => 'integer',
        'total_boards_ordered' => 'integer',
        'boards_produced' => 'integer',
        'boards_assembled' => 'integer',
        'nextcloud_folder_created' => 'boolean',
        'components_tracked' => 'boolean',
        'total_components_cost' => 'decimal:2',
        'completion_percentage' => 'decimal:2',
        'ai_priority_score' => 'integer',
        'ai_priority_data' => 'json',
        'ai_priority_calculated_at' => 'datetime',
    ];

    /**
     * Get the customer for this project.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the project manager (user).
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    /**
     * Get the project priority.
     */
    public function priority(): BelongsTo
    {
        return $this->belongsTo(ProjectPriority::class, 'priority_id');
    }
    
    /**
     * Get the project progress.
     */
    public function progress(): BelongsTo
    {
        return $this->belongsTo(ProjectProgress::class, 'progress_id');
    }
    
    /**
     * Get project milestones.
     */
    public function milestones(): BelongsToMany
    {
        return $this->belongsToMany(Milestone::class, 'project_milestone')
            ->withPivot(['target_date', 'completed_date', 'notes', 'is_completed', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderBy('name');
    }
    
    /**
     * Get completed milestones count.
     */
    public function getCompletedMilestonesCountAttribute(): int
    {
        return $this->milestones()->wherePivot('is_completed', true)->count();
    }
    
    /**
     * Get total milestones count.
     */
    public function getTotalMilestonesCountAttribute(): int
    {
        return $this->milestones()->count();
    }
    
    /**
     * Get completion percentage based on milestones.
     */
    public function getMilestoneCompletionPercentageAttribute(): float
    {
        if ($this->total_milestones_count === 0) {
            return 0;
        }
        
        return round(($this->completed_milestones_count / $this->total_milestones_count) * 100, 1);
    }

    /**
     * Get all quotations for this project (unified many-to-many relation).
     */
    public function quotations(): BelongsToMany
    {
        return $this->belongsToMany(Quotation::class, 'project_quotation')
            ->withTimestamps();
    }
    
    /**
     * Get all linked quotations (alias for backward compatibility).
     */
    public function linkedQuotations(): BelongsToMany
    {
        return $this->quotations();
    }
    
    /**
     * Get all project documents.
     */
    public function projectDocuments(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
    }
    
    /**
     * Get system instances for this project.
     */
    public function systemInstances(): HasMany
    {
        return $this->hasMany(ProjectSystemInstance::class)->active();
    }
    
    /**
     * Get overall system engineering completion percentage.
     */
    public function getSystemEngineeringCompletionAttribute(): float
    {
        $instances = $this->systemInstances;
        if ($instances->isEmpty()) return 0;
        
        $totalPercentage = $instances->sum('completion_percentage');
        return round($totalPercentage / $instances->count(), 1);
    }

    /**
     * Get all documents for this project.
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Get all inventory movements for this project.
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'reference_id')
            ->where('reference_type', 'project');
    }

    /**
     * Check if project is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date && 
               $this->due_date->isPast() && 
               !in_array($this->status, ['completed', 'cancelled']);
    }

    /**
     * Check if project is over budget.
     */
    public function isOverBudget(): bool
    {
        return $this->budget && 
               $this->actual_cost && 
               $this->actual_cost > $this->budget;
    }

    /**
     * Get the status badge color.
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'planning' => 'gray',
            'in_progress' => 'primary',
            'completed' => 'success',
            'cancelled' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Get the priority badge color.
     */
    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            'low' => 'gray',
            'medium' => 'warning',
            'high' => 'danger',
            'urgent' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Scope for active projects.
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['completed', 'cancelled']);
    }

    /**
     * Scope for overdue projects.
     */
    public function scopeOverdue($query)
    {
        return $query->active()
            ->whereNotNull('due_date')
            ->where('due_date', '<', now());
    }

    /**
     * Get all BOMs for this project.
     */
    public function boms(): HasMany
    {
        return $this->hasMany(ProjectBom::class);
    }

    /**
     * Get all PCB files for this project.
     */
    public function pcbFiles(): HasMany
    {
        return $this->hasMany(ProjectPcbFile::class);
    }

    /**
     * Get all user manuals for this project.
     */
    public function userManuals(): HasMany
    {
        return $this->hasMany(UserManual::class);
    }

    /**
     * Get all tasks for this project.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class)->orderBy('sort_order');
    }

    /**
     * Get active tasks for this project.
     */
    public function activeTasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class)->where('status', '!=', 'cancelled')->orderBy('sort_order');
    }

    /**
     * Get all time entries for this project.
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /**
     * Get the latest BOM.
     */
    public function latestBom()
    {
        return $this->hasOne(ProjectBom::class)->latest();
    }

    /**
     * Calculate total boards ordered from all quotations.
     */
    public function calculateTotalBoardsOrdered(): int
    {
        return $this->quotations()
            ->where('status', 'accepted')
            ->whereNotNull('boards_quantity')
            ->sum('boards_quantity');
    }

    /**
     * Update total boards ordered automatically.
     */
    public function updateTotalBoardsOrdered(): void
    {
        $this->update(['total_boards_ordered' => $this->calculateTotalBoardsOrdered()]);
    }

    /**
     * Calculate budget from accepted quotations.
     */
    public function calculateBudgetFromQuotations(): float
    {
        return $this->quotations()
            ->where('status', 'accepted')
            ->sum('total');
    }

    /**
     * Update budget automatically from quotations.
     */
    public function updateBudgetFromQuotations(): void
    {
        // Don't update if budget is set to manual
        if ($this->manual_budget) {
            return;
        }

        $calculatedBudget = $this->calculateBudgetFromQuotations();

        if ($calculatedBudget > 0) {
            $this->update(['budget' => $calculatedBudget]);
        }
    }

    /**
     * Get production progress percentage.
     */
    public function getProductionProgressAttribute(): int
    {
        if ($this->total_boards_ordered == 0) return 0;
        
        return round(($this->boards_produced / $this->total_boards_ordered) * 100);
    }

    /**
     * Get assembly progress percentage.
     */
    public function getAssemblyProgressAttribute(): int
    {
        if ($this->boards_produced == 0) return 0;

        return round(($this->boards_assembled / $this->boards_produced) * 100);
    }

    /**
     * Get all component allocations for this project.
     */
    public function componentAllocations(): HasMany
    {
        return $this->hasMany(\App\Models\ProjectComponentAllocation::class);
    }

    /**
     * Get all invoices issued for this project.
     */
    public function invoicesIssued(): HasMany
    {
        return $this->hasMany(InvoiceIssued::class);
    }

    /**
     * Get all invoices received for this project.
     */
    public function invoicesReceived(): HasMany
    {
        return $this->hasMany(InvoiceReceived::class);
    }

    /**
     * Get all payment milestones for this project.
     */
    public function paymentMilestones(): HasMany
    {
        return $this->hasMany(PaymentMilestone::class);
    }

    /**
     * Get inventory movements for this project.
     */
    public function projectInventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'destination_project_id');
    }

    /**
     * Get all board assembly logs for this project.
     */
    public function boardAssemblyLogs(): HasMany
    {
        return $this->hasMany(BoardAssemblyLog::class)->orderBy('assembly_date', 'desc');
    }

    /**
     * Update completion percentage based on milestones.
     */
    public function updateCompletionPercentage(): void
    {
        $totalMilestones = $this->milestones()->count();

        if ($totalMilestones === 0) {
            $this->update(['completion_percentage' => 0]);
            return;
        }

        $completedMilestones = $this->milestones()
            ->wherePivot('is_completed', true)
            ->count();

        $percentage = round(($completedMilestones / $totalMilestones) * 100, 2);

        $this->update(['completion_percentage' => $percentage]);

        \Illuminate\Support\Facades\Log::info("Project completion percentage updated", [
            'project_id' => $this->id,
            'project_code' => $this->code,
            'completed' => $completedMilestones,
            'total' => $totalMilestones,
            'percentage' => $percentage,
        ]);
    }

    /**
     * Calculate completion percentage without saving.
     */
    public function calculateCompletionPercentage(): float
    {
        $totalMilestones = $this->milestones()->count();

        if ($totalMilestones === 0) {
            return 0;
        }

        $completedMilestones = $this->milestones()
            ->wherePivot('is_completed', true)
            ->count();

        return round(($completedMilestones / $totalMilestones) * 100, 2);
    }

    /**
     * Get next uncompleted milestone.
     */
    public function getNextMilestone(): ?Milestone
    {
        return $this->milestones()
            ->wherePivot('is_completed', false)
            ->orderByPivot('sort_order')
            ->first();
    }

    /**
     * Check if project is nearing deadline.
     */
    public function isNearingDeadline(int $days = 7): bool
    {
        if (!$this->due_date) {
            return false;
        }

        $daysLeft = now()->diffInDays($this->due_date, false);
        return $daysLeft >= 0 && $daysLeft <= $days;
    }

    /**
     * Get days until deadline.
     */
    public function getDaysUntilDeadline(): ?int
    {
        if (!$this->due_date) {
            return null;
        }

        return now()->diffInDays($this->due_date, false);
    }
}