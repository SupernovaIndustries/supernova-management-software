<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AssemblyChecklistResponse extends Model
{
    protected $fillable = [
        'checklist_id',
        'item_id',
        'user_id',
        'response_data',
        'status',
        'comments',
        'failure_reason',
        'attachments',
        'measured_value',
        'measurement_unit',
        'within_tolerance',
        'completed_at',
        'reviewed_by',
        'reviewed_at',
        'review_comments',
    ];

    protected $casts = [
        'response_data' => 'array',
        'attachments' => 'array',
        'within_tolerance' => 'boolean',
        'completed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'measured_value' => 'decimal:4',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::updating(function ($response) {
            // Auto-set completion timestamp when status changes to completed
            if ($response->isDirty('status') && $response->status === 'completed') {
                $response->completed_at = now();
            }
        });
    }

    /**
     * Get the checklist this response belongs to.
     */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(AssemblyChecklist::class, 'checklist_id');
    }

    /**
     * Get the checklist item this response is for.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(AssemblyChecklistItem::class, 'item_id');
    }

    /**
     * Get the user who completed this response.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the reviewer.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get status options.
     */
    public static function getStatusOptions(): array
    {
        return [
            'pending' => 'Pending',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'skipped' => 'Skipped',
            'needs_review' => 'Needs Review',
        ];
    }

    /**
     * Mark as completed with response data.
     */
    public function complete($responseData, ?string $comments = null): void
    {
        $validationErrors = $this->item->validateResponse($responseData);
        
        if (!empty($validationErrors)) {
            throw new \InvalidArgumentException('Validation failed: ' . implode(', ', $validationErrors));
        }

        // Handle measurement items specifically
        if ($this->item->type === 'measurement') {
            $this->measured_value = $responseData;
            $this->within_tolerance = empty($validationErrors);
            
            if (!empty($this->item->validation_rules['unit'])) {
                $this->measurement_unit = $this->item->validation_rules['unit'];
            }
        }

        $this->update([
            'response_data' => $responseData,
            'status' => 'completed',
            'comments' => $comments,
            'completed_at' => now(),
        ]);

        // Update checklist completion stats
        $this->checklist->updateCompletionStats();
    }

    /**
     * Mark as failed with reason.
     */
    public function markAsFailed(string $reason, ?string $comments = null): void
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
            'comments' => $comments,
        ]);

        $this->checklist->updateCompletionStats();
    }

    /**
     * Skip this item.
     */
    public function skip(string $reason): void
    {
        $this->update([
            'status' => 'skipped',
            'comments' => $reason,
        ]);

        $this->checklist->updateCompletionStats();
    }

    /**
     * Mark as needing review.
     */
    public function markForReview(string $reason): void
    {
        $this->update([
            'status' => 'needs_review',
            'comments' => $reason,
        ]);
    }

    /**
     * Review and approve/reject.
     */
    public function review(int $reviewerId, bool $approved, ?string $comments = null): void
    {
        $this->update([
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'review_comments' => $comments,
            'status' => $approved ? 'completed' : 'failed',
        ]);

        if (!$approved && $comments) {
            $this->failure_reason = $comments;
            $this->save();
        }

        $this->checklist->updateCompletionStats();
    }

    /**
     * Add attachment (photo, file, etc.).
     */
    public function addAttachment(string $filePath, string $type = 'file'): void
    {
        $attachments = $this->attachments ?? [];
        
        $attachments[] = [
            'path' => $filePath,
            'type' => $type,
            'uploaded_at' => now()->toISOString(),
            'original_name' => basename($filePath),
            'size' => Storage::size($filePath),
        ];

        $this->update(['attachments' => $attachments]);
    }

    /**
     * Get attachment URLs for display.
     */
    public function getAttachmentUrls(): array
    {
        if (!$this->attachments) {
            return [];
        }

        return collect($this->attachments)->map(function ($attachment) {
            return [
                'url' => Storage::url($attachment['path']),
                'type' => $attachment['type'] ?? 'file',
                'name' => $attachment['original_name'] ?? basename($attachment['path']),
                'uploaded_at' => $attachment['uploaded_at'],
            ];
        })->toArray();
    }

    /**
     * Get formatted response for display.
     */
    public function getFormattedResponse(): string
    {
        if (!$this->response_data) {
            return 'No response';
        }

        switch ($this->item->type) {
            case 'checkbox':
                return $this->response_data ? 'Yes' : 'No';
            
            case 'number':
                return (string) $this->response_data;
            
            case 'measurement':
                $unit = $this->measurement_unit ?? '';
                $tolerance = $this->within_tolerance ? ' ✓' : ' ⚠️';
                return $this->measured_value . $unit . $tolerance;
            
            case 'multiselect':
                if (is_array($this->response_data)) {
                    return implode(', ', $this->response_data);
                }
                return (string) $this->response_data;
            
            case 'text':
                return (string) $this->response_data;
            
            case 'photo':
            case 'file':
            case 'signature':
                $count = count($this->attachments ?? []);
                return $count . ' file(s) attached';
            
            default:
                return json_encode($this->response_data);
        }
    }

    /**
     * Check if response needs supervisor attention.
     */
    public function needsSupervisorAttention(): bool
    {
        return $this->status === 'failed' || 
               $this->status === 'needs_review' ||
               ($this->item->is_critical && $this->status === 'completed' && !$this->within_tolerance);
    }

    /**
     * Get time spent on this item.
     */
    public function getTimeSpentMinutes(): ?int
    {
        if (!$this->created_at || !$this->completed_at) {
            return null;
        }

        return $this->created_at->diffInMinutes($this->completed_at);
    }

    /**
     * Scope by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope pending responses.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope completed responses.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope failed responses.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope responses needing review.
     */
    public function scopeNeedsReview($query)
    {
        return $query->where('status', 'needs_review');
    }

    /**
     * Scope by checklist.
     */
    public function scopeForChecklist($query, int $checklistId)
    {
        return $query->where('checklist_id', $checklistId);
    }

    /**
     * Scope by user.
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
