<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProjectDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'milestone_id',
        'name',
        'type',
        'file_path',
        'nextcloud_path',
        'uploaded_to_nextcloud',
        'local_file_deleted',
        'original_filename',
        'mime_type',
        'file_size',
        'description',
        'tags',
        'document_date',
        'amount',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'document_date' => 'date',
        'amount' => 'decimal:2',
        'file_size' => 'integer',
        'is_active' => 'boolean',
        'uploaded_to_nextcloud' => 'boolean',
        'local_file_deleted' => 'boolean',
        'tags' => 'array',
    ];

    /**
     * Get the project that owns this document.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the milestone that owns this document.
     */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    /**
     * Get document type options.
     */
    public static function getDocumentTypes(): array
    {
        return [
            'invoice_received' => 'Fattura Ricevuta',
            'invoice_issued' => 'Fattura Emessa',
            'customs' => 'Documenti Dogana',
            'kicad_project' => 'Progetto KiCad',
            'kicad_library' => 'Librerie KiCad',
            'gerber' => 'File Gerber',
            'bom' => 'Bill of Materials',
            'bom_interactive' => 'BOM Interattiva',
            '3d_model' => 'Modello 3D (STL/STEP)',
            '3d_case' => 'Case/Enclosure 3D',
            '3d_mechanical' => 'Progetto Meccanico 3D',
            'cad_drawing' => 'Disegno CAD',
            'complaint' => 'Reclamo Cliente',
            'error_report' => 'Report Errori',
            'assembly_instructions' => 'Istruzioni Assemblaggio',
            'test_report' => 'Report Test',
            'certification' => 'Certificazioni',
            'datasheet' => 'Datasheet',
            'other' => 'Altro',
        ];
    }

    /**
     * Get the file URL.
     */
    public function getFileUrlAttribute(): string
    {
        return Storage::url($this->file_path);
    }

    /**
     * Get formatted file size.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        if (!$this->file_size) return 'N/A';
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unit = 0;
        
        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }
        
        return round($size, 2) . ' ' . $units[$unit];
    }

    /**
     * Check if the document is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }

    /**
     * Check if the document is a PDF.
     */
    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Check if the document is a 3D file.
     */
    public function is3DFile(): bool
    {
        $extension = strtolower(pathinfo($this->original_filename ?? '', PATHINFO_EXTENSION));
        return in_array($extension, ['stl', 'step', 'stp', 'iges', 'igs', 'obj', '3mf', 'amf']);
    }

    /**
     * Check if the document is a CAD file.
     */
    public function isCadFile(): bool
    {
        $extension = strtolower(pathinfo($this->original_filename ?? '', PATHINFO_EXTENSION));
        return in_array($extension, ['dxf', 'dwg', 'f3d', 'ipt', 'sldprt']);
    }

    /**
     * Get the file icon based on type.
     */
    public function getFileIcon(): string
    {
        if ($this->isImage()) return 'heroicon-o-photo';
        if ($this->isPdf()) return 'heroicon-o-document-text';
        if ($this->is3DFile()) return 'heroicon-o-cube';
        if ($this->isCadFile()) return 'heroicon-o-square-3-stack-3d';
        
        return match($this->type) {
            'invoice_received', 'invoice_issued' => 'heroicon-o-receipt-percent',
            'kicad_project', 'kicad_library' => 'heroicon-o-cpu-chip',
            'gerber' => 'heroicon-o-squares-2x2',
            'bom', 'bom_interactive' => 'heroicon-o-clipboard-document-list',
            '3d_model', '3d_case', '3d_mechanical' => 'heroicon-o-cube',
            'cad_drawing' => 'heroicon-o-square-3-stack-3d',
            default => 'heroicon-o-document',
        };
    }

    /**
     * Scope for active documents.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by document type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
