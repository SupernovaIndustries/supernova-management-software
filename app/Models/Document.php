<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Laravel\Scout\Searchable;

class Document extends Model
{
    use HasFactory, Searchable;

    protected $fillable = [
        'title',
        'description',
        'type',
        'file_path',
        'syncthing_path',
        'nextcloud_path',
        'uploaded_to_nextcloud',
        'local_file_deleted',
        'mime_type',
        'file_size',
        'documentable_type',
        'documentable_id',
        'metadata',
        'uploaded_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'file_size' => 'integer',
        'uploaded_to_nextcloud' => 'boolean',
        'local_file_deleted' => 'boolean',
    ];

    /**
     * Get the owning documentable model.
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who uploaded this document.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the document URL.
     */
    public function getUrlAttribute(): ?string
    {
        if ($this->file_path) {
            return Storage::url($this->file_path);
        }
        return null;
    }

    /**
     * Get the file size in human readable format.
     */
    public function getHumanFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if document exists in Syncthing.
     */
    public function existsInSyncthing(): bool
    {
        if (!$this->syncthing_path) {
            return false;
        }

        $disk = app('syncthing.paths')->disk('documents');
        return $disk->exists($this->syncthing_path);
    }

    /**
     * Get the searchable array for Scout.
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
        ];
    }

    /**
     * Scope for documents by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}