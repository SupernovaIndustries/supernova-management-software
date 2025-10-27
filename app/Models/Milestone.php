<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Milestone extends Model
{
    protected $fillable = [
        'name',
        'description',
        'icon',
        'color',
        'category',
        'sort_order',
        'is_active',
        'deadline',
        'email_notifications',
        'notification_days_before',
        'last_notification_sent',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'email_notifications' => 'boolean',
        'deadline' => 'date',
        'last_notification_sent' => 'datetime',
    ];

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_milestone')
            ->withPivot(['target_date', 'completed_date', 'notes', 'is_completed', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * Get all documents attached to this milestone.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
    }
}
