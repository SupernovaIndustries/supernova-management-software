<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class ArUcoMarker extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'marker_id',
        'type',
        'name',
        'description',
        'data',
        'component_id',
        'project_id',
        'location',
        'is_active',
        'last_scanned_at',
        'scan_count',
        'created_by',
    ];

    protected $casts = [
        'marker_id' => 'integer',
        'data' => 'array',
        'last_scanned_at' => 'datetime',
        'is_active' => 'boolean',
        'scan_count' => 'integer',
    ];

    /**
     * Get the component associated with this marker.
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    /**
     * Get the project associated with this marker.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user who created this marker.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get marker type options.
     */
    public static function getTypeOptions(): array
    {
        return [
            'component' => 'Component Tracking',
            'location' => 'Location/Storage',
            'checklist' => 'Assembly Checklist',
            'project' => 'Project Reference',
            'generic' => 'Generic Marker',
        ];
    }

    /**
     * Record a scan of this marker.
     */
    public function recordScan(?User $user = null, ?string $location = null): void
    {
        $this->increment('scan_count');
        $this->update([
            'last_scanned_at' => now(),
            'location' => $location ?? $this->location,
        ]);

        // Log the scan activity
        if ($user) {
            activity('aruco_scan')
                ->performedOn($this)
                ->by($user)
                ->withProperties([
                    'marker_id' => $this->marker_id,
                    'type' => $this->type,
                    'location' => $location,
                    'scan_count' => $this->scan_count,
                ])
                ->log("ArUco marker {$this->marker_id} scanned");
        }
    }

    /**
     * Generate the ArUco marker image URL.
     */
    public function getMarkerImageUrlAttribute(): string
    {
        // Generate ArUco marker image using online service
        $size = 200;
        $border = 1;
        return "https://api.qrserver.com/v1/create-aruco/?size={$size}x{$size}&data={$this->marker_id}&download=0&border={$border}";
    }

    /**
     * Get the display name for this marker.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->name) {
            return $this->name;
        }

        return match ($this->type) {
            'component' => $this->component?->name ?? "Component Marker #{$this->marker_id}",
            'location' => $this->location ?? "Location Marker #{$this->marker_id}",
            'checklist' => "Checklist Marker #{$this->marker_id}",
            'project' => $this->project?->name ?? "Project Marker #{$this->marker_id}",
            default => "ArUco Marker #{$this->marker_id}",
        };
    }

    /**
     * Get the description for mobile display.
     */
    public function getMobileDescriptionAttribute(): string
    {
        if ($this->description) {
            return $this->description;
        }

        return match ($this->type) {
            'component' => "Track and identify component: " . ($this->component?->part_number ?? 'Unknown'),
            'location' => "Storage location: " . ($this->location ?? 'Unknown'),
            'checklist' => "Assembly checklist access",
            'project' => "Project reference: " . ($this->project?->name ?? 'Unknown'),
            default => "Generic ArUco marker",
        };
    }

    /**
     * Get related data for mobile app.
     */
    public function getMobileDataAttribute(): array
    {
        $baseData = [
            'marker_id' => $this->marker_id,
            'type' => $this->type,
            'name' => $this->display_name,
            'description' => $this->mobile_description,
            'location' => $this->location,
            'scan_count' => $this->scan_count,
            'last_scanned' => $this->last_scanned_at?->toISOString(),
        ];

        switch ($this->type) {
            case 'component':
                if ($this->component) {
                    $baseData['component'] = [
                        'id' => $this->component->id,
                        'name' => $this->component->name,
                        'part_number' => $this->component->part_number,
                        'quantity' => $this->component->quantity,
                        'location' => $this->component->location,
                    ];
                }
                break;

            case 'project':
                if ($this->project) {
                    $baseData['project'] = [
                        'id' => $this->project->id,
                        'name' => $this->project->name,
                        'status' => $this->project->status,
                        'progress' => $this->project->progress_percentage ?? 0,
                    ];
                }
                break;

            case 'location':
                $baseData['components_at_location'] = Component::where('location', $this->location)->count();
                break;
        }

        // Merge custom data
        if ($this->data) {
            $baseData = array_merge($baseData, $this->data);
        }

        return $baseData;
    }

    /**
     * Check if marker can be deleted.
     */
    public function canBeDeleted(): bool
    {
        // Cannot delete if linked to active resources
        return !($this->component_id || $this->project_id);
    }

    /**
     * Scope for active markers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for markers by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for recently scanned markers.
     */
    public function scopeRecentlyScanned($query, int $hours = 24)
    {
        return $query->where('last_scanned_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope for markers at location.
     */
    public function scopeAtLocation($query, string $location)
    {
        return $query->where('location', $location);
    }

    /**
     * Activity log configuration.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['marker_id', 'type', 'name', 'location', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}