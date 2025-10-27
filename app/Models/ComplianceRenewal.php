<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ComplianceRenewal extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_compliance_document_id',
        'renewal_due_date',
        'renewal_type',
        'renewal_requirements',
        'status',
        'reminder_sent_at',
        'assigned_to',
        'notes',
    ];

    protected $casts = [
        'renewal_due_date' => 'date',
        'reminder_sent_at' => 'date',
    ];

    /**
     * Get the compliance document this renewal belongs to.
     */
    public function projectComplianceDocument(): BelongsTo
    {
        return $this->belongsTo(ProjectComplianceDocument::class);
    }

    /**
     * Get the user assigned to this renewal.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Scope for pending renewals.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for overdue renewals.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', 'pending')
                    ->where('renewal_due_date', '<', now()->toDate());
    }

    /**
     * Scope for renewals due soon.
     */
    public function scopeDueSoon(Builder $query, int $days = 30): Builder
    {
        return $query->where('status', 'pending')
                    ->whereBetween('renewal_due_date', [
                        now()->toDate(),
                        now()->addDays($days)->toDate()
                    ]);
    }

    /**
     * Scope by renewal type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('renewal_type', $type);
    }

    /**
     * Get status badge color.
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'completed' => 'success',
            'in_progress' => 'info',
            'overdue' => 'danger',
            'pending' => $this->isOverdue() ? 'danger' : 'warning',
            default => 'gray',
        };
    }

    /**
     * Get formatted renewal type.
     */
    public function getRenewalTypeNameAttribute(): string
    {
        return match($this->renewal_type) {
            'automatic' => 'Rinnovo Automatico',
            'testing_required' => 'Test Richiesti',
            'documentation_update' => 'Aggiornamento Documentazione',
            default => ucfirst($this->renewal_type),
        };
    }

    /**
     * Get formatted status.
     */
    public function getStatusNameAttribute(): string
    {
        return match($this->status) {
            'pending' => $this->isOverdue() ? 'Scaduto' : 'In Attesa',
            'in_progress' => 'In Corso',
            'completed' => 'Completato',
            'overdue' => 'Scaduto',
            default => ucfirst($this->status),
        };
    }

    /**
     * Check if renewal is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->status === 'pending' && 
               $this->renewal_due_date && 
               $this->renewal_due_date->isPast();
    }

    /**
     * Check if renewal is due soon.
     */
    public function isDueSoon(int $days = 30): bool
    {
        return $this->status === 'pending' && 
               $this->renewal_due_date && 
               $this->renewal_due_date->isFuture() && 
               $this->renewal_due_date->lte(now()->addDays($days));
    }

    /**
     * Get days until renewal due date.
     */
    public function getDaysUntilDueAttribute(): ?int
    {
        if (!$this->renewal_due_date) return null;
        
        return now()->diffInDays($this->renewal_due_date, false);
    }

    /**
     * Get urgency level.
     */
    public function getUrgencyAttribute(): string
    {
        if ($this->isOverdue()) {
            return 'critical';
        }
        
        $daysUntilDue = $this->getDaysUntilDueAttribute();
        
        if ($daysUntilDue <= 7) {
            return 'high';
        }
        
        if ($daysUntilDue <= 30) {
            return 'medium';
        }
        
        return 'low';
    }

    /**
     * Get urgency color.
     */
    public function getUrgencyColorAttribute(): string
    {
        return match($this->getUrgencyAttribute()) {
            'critical' => 'danger',
            'high' => 'warning',
            'medium' => 'info',
            'low' => 'success',
            default => 'gray',
        };
    }

    /**
     * Check if reminder should be sent.
     */
    public function shouldSendReminder(): bool
    {
        // Send reminder 30 days before due date
        $reminderDate = $this->renewal_due_date?->subDays(30);
        
        return $this->status === 'pending' && 
               $reminderDate && 
               $reminderDate->lte(now()) && 
               !$this->reminder_sent_at;
    }

    /**
     * Mark reminder as sent.
     */
    public function markReminderSent(): void
    {
        $this->update(['reminder_sent_at' => now()]);
    }

    /**
     * Get priority level based on document criticality and due date.
     */
    public function getPriorityAttribute(): string
    {
        $document = $this->projectComplianceDocument;
        $standard = $document->complianceStandard;
        
        // Critical standards get higher priority
        $criticalStandards = ['CE', 'FCC', 'RoHS'];
        $isCriticalStandard = in_array($standard?->code, $criticalStandards);
        
        if ($this->isOverdue()) {
            return 'urgent';
        }
        
        if ($isCriticalStandard && $this->isDueSoon(14)) {
            return 'high';
        }
        
        if ($this->isDueSoon(7)) {
            return 'high';
        }
        
        if ($this->isDueSoon(30)) {
            return 'medium';
        }
        
        return 'low';
    }

    /**
     * Get priority color.
     */
    public function getPriorityColorAttribute(): string
    {
        return match($this->getPriorityAttribute()) {
            'urgent' => 'danger',
            'high' => 'warning',
            'medium' => 'info',
            'low' => 'success',
            default => 'gray',
        };
    }

    /**
     * Get full renewal identifier.
     */
    public function getFullIdentifierAttribute(): string
    {
        $document = $this->projectComplianceDocument;
        $project = $document->project;
        $standard = $document->complianceStandard;
        
        return sprintf(
            'REN-%s-%s-%s',
            $standard?->code ?? 'UNK',
            $project->code ?? 'UNK',
            $this->renewal_due_date?->format('Y-m') ?? 'UNK'
        );
    }

    /**
     * Get estimated effort for renewal.
     */
    public function getEstimatedEffortAttribute(): string
    {
        return match($this->renewal_type) {
            'automatic' => '1-2 ore',
            'documentation_update' => '1-3 giorni',
            'testing_required' => '2-4 settimane',
            default => 'Non specificato',
        };
    }

    /**
     * Check if renewal requires external support.
     */
    public function requiresExternalSupport(): bool
    {
        return $this->renewal_type === 'testing_required';
    }

    /**
     * Get renewal checklist.
     */
    public function getChecklistAttribute(): array
    {
        $baseChecklist = [
            'Verifica scadenza documento originale',
            'Raccolta documentazione necessaria',
            'Verifica conformità requisiti attuali',
        ];
        
        return match($this->renewal_type) {
            'automatic' => array_merge($baseChecklist, [
                'Aggiornamento date nel sistema',
                'Generazione nuovo documento',
            ]),
            'documentation_update' => array_merge($baseChecklist, [
                'Aggiornamento specifiche tecniche',
                'Revisione documentazione tecnica',
                'Approvazione interna',
                'Generazione documento aggiornato',
            ]),
            'testing_required' => array_merge($baseChecklist, [
                'Programmazione test necessari',
                'Coordinamento con laboratorio esterno',
                'Esecuzione test',
                'Analisi risultati',
                'Generazione nuovo certificato',
            ]),
            default => $baseChecklist,
        };
    }
}