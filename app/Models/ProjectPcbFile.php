<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPcbFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'file_name',
        'file_type',
        'file_path',
        'file_size',
        'file_hash',
        'folder_path',
        'version',
        'description',
        'metadata',
        'is_primary',
        'is_backup',
        'change_type',
        'uploaded_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_primary' => 'boolean',
        'is_backup' => 'boolean',
        'file_size' => 'integer',
    ];

    /**
     * Get the project this PCB file belongs to.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user who uploaded this file.
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the full file path.
     */
    public function getFullPathAttribute(): string
    {
        return $this->folder_path . '/' . $this->file_name;
    }

    /**
     * Check if file exists in filesystem.
     */
    public function exists(): bool
    {
        $disk = app('syncthing.paths')->disk('clients');
        return $disk->exists($this->full_path);
    }

    /**
     * Get file size.
     */
    public function getFileSizeAttribute(): ?int
    {
        if ($this->exists()) {
            $disk = app('syncthing.paths')->disk('clients');
            return $disk->size($this->full_path);
        }
        return null;
    }

    /**
     * Get human readable file size.
     */
    public function getHumanFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        
        if ($bytes === null) {
            return 'N/A';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Scope for KiCad files.
     */
    public function scopeKicad($query)
    {
        return $query->where('file_type', 'kicad');
    }

    /**
     * Scope for Gerber files.
     */
    public function scopeGerber($query)
    {
        return $query->where('file_type', 'gerber');
    }
}