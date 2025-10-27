<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardQrCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'board_assembly_log_id',
        'board_number',
        'qr_data',
        'qr_file_path',
        'generated_at',
    ];

    protected $casts = [
        'board_number' => 'integer',
        'generated_at' => 'datetime',
    ];

    /**
     * Get the assembly log this QR code belongs to.
     */
    public function assemblyLog(): BelongsTo
    {
        return $this->belongsTo(BoardAssemblyLog::class, 'board_assembly_log_id');
    }

    /**
     * Get the full Nextcloud URL for the QR code.
     */
    public function getFullUrlAttribute(): string
    {
        // This would need the Nextcloud base URL from config
        return config('nextcloud.base_url') . '/' . $this->qr_file_path;
    }

    /**
     * Get the filename from the path.
     */
    public function getFilenameAttribute(): string
    {
        return basename($this->qr_file_path);
    }
}
