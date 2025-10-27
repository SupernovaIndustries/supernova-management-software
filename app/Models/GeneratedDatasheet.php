<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class GeneratedDatasheet extends Model
{
    use HasFactory;

    protected $fillable = [
        'datasheet_template_id',
        'generatable_type',
        'generatable_id',
        'title',
        'version',
        'description',
        'generated_data',
        'file_path',
        'file_format',
        'file_size',
        'file_hash',
        'generated_by',
        'generated_at',
    ];

    protected $casts = [
        'generated_data' => 'array',
        'file_size' => 'integer',
        'generated_at' => 'datetime',
    ];

    /**
     * Get the template used for generation.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(DatasheetTemplate::class, 'datasheet_template_id');
    }

    /**
     * Get the model that was used to generate this datasheet.
     */
    public function generatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who generated this datasheet.
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Get human readable file size.
     */
    public function getHumanFileSizeAttribute(): string
    {
        if (!$this->file_size) return 'N/A';
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $this->file_size;
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if file exists on disk.
     */
    public function fileExists(): bool
    {
        return \Illuminate\Support\Facades\Storage::disk('local')->exists($this->file_path);
    }

    /**
     * Get download URL.
     */
    public function getDownloadUrlAttribute(): string
    {
        return route('datasheets.download', $this->id);
    }
}
