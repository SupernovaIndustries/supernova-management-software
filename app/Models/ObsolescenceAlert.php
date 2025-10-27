<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObsolescenceAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'component_id',
        'alert_type',
        'severity',
        'title',
        'message',
        'affected_projects',
        'alert_date',
        'acknowledged_at',
        'acknowledged_by',
        'is_resolved',
    ];

    protected $casts = [
        'affected_projects' => 'array',
        'alert_date' => 'datetime',
        'acknowledged_at' => 'datetime',
        'is_resolved' => 'boolean',
    ];

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * Get alert type label
     */
    public function getAlertTypeLabelAttribute(): string
    {
        return match($this->alert_type) {
            'eol_warning' => 'EOL Warning',
            'eol_imminent' => 'EOL Imminent',
            'last_time_buy' => 'Last Time Buy',
            'obsolete' => 'Obsolete',
            default => 'Unknown'
        };
    }

    /**
     * Get severity color
     */
    public function getSeverityColorAttribute(): string
    {
        return match($this->severity) {
            'low' => 'success',
            'medium' => 'warning',
            'high' => 'danger',
            'critical' => 'danger',
            default => 'secondary'
        };
    }

    /**
     * Get affected projects count
     */
    public function getAffectedProjectsCountAttribute(): int
    {
        return is_array($this->affected_projects) ? count($this->affected_projects) : 0;
    }

    /**
     * Check if alert is acknowledged
     */
    public function isAcknowledged(): bool
    {
        return !is_null($this->acknowledged_at);
    }

    /**
     * Acknowledge alert
     */
    public function acknowledge(User $user): void
    {
        $this->update([
            'acknowledged_at' => now(),
            'acknowledged_by' => $user->id,
        ]);
    }

    /**
     * Scope for unacknowledged alerts
     */
    public function scopeUnacknowledged($query)
    {
        return $query->whereNull('acknowledged_at');
    }

    /**
     * Scope for unresolved alerts
     */
    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }

    /**
     * Scope by severity
     */
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope for critical alerts
     */
    public function scopeCritical($query)
    {
        return $query->whereIn('severity', ['high', 'critical']);
    }
}