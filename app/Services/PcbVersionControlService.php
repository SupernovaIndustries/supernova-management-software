<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectPcbFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Http\UploadedFile;

class PcbVersionControlService
{
    /**
     * Upload and version a new PCB file
     */
    public function uploadPcbFile(
        Project $project, 
        UploadedFile $file, 
        string $fileType,
        ?string $description = null
    ): ProjectPcbFile {
        // Generate version number
        $latestVersion = ProjectPcbFile::where('project_id', $project->id)
            ->where('file_type', $fileType)
            ->max('version') ?? 0;
        
        $newVersion = $latestVersion + 1;

        // Generate file path with versioning
        $fileName = $this->generateVersionedFileName($project, $file, $fileType, $newVersion);
        $filePath = "projects/{$project->id}/pcb/{$fileName}";

        // Store file
        $storedPath = Storage::disk('syncthing')->putFileAs(
            dirname($filePath),
            $file,
            basename($filePath)
        );

        // Create database record
        return ProjectPcbFile::create([
            'project_id' => $project->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $storedPath,
            'file_type' => $fileType,
            'version' => $newVersion,
            'file_size' => $file->getSize(),
            'file_hash' => hash_file('sha256', $file->getRealPath()),
            'description' => $description,
            'uploaded_by' => auth()->id(),
        ]);
    }

    /**
     * Generate versioned file name
     */
    private function generateVersionedFileName(
        Project $project, 
        UploadedFile $file, 
        string $fileType, 
        int $version
    ): string {
        $extension = $file->getClientOriginalExtension();
        $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        
        return "{$project->code}_{$fileType}_v{$version}_{$baseName}.{$extension}";
    }

    /**
     * Compare two PCB file versions
     */
    public function compareVersions(ProjectPcbFile $file1, ProjectPcbFile $file2): array
    {
        $comparison = [
            'files' => [
                'file1' => $this->getFileInfo($file1),
                'file2' => $this->getFileInfo($file2),
            ],
            'differences' => [],
            'similarity_score' => 0,
        ];

        // Basic file comparison
        if ($file1->file_hash === $file2->file_hash) {
            $comparison['similarity_score'] = 1.0;
            $comparison['differences'][] = 'Files are identical';
            return $comparison;
        }

        // File size comparison
        $sizeDiff = abs($file1->file_size - $file2->file_size);
        $sizeChangePercentage = $file1->file_size > 0 ? ($sizeDiff / $file1->file_size) * 100 : 0;
        
        $comparison['differences'][] = [
            'type' => 'file_size',
            'old_size' => $file1->file_size,
            'new_size' => $file2->file_size,
            'change_bytes' => $file2->file_size - $file1->file_size,
            'change_percentage' => $sizeChangePercentage,
        ];

        // Version difference
        $comparison['differences'][] = [
            'type' => 'version',
            'old_version' => $file1->version,
            'new_version' => $file2->version,
            'version_increment' => $file2->version - $file1->version,
        ];

        // Time difference
        $comparison['differences'][] = [
            'type' => 'time',
            'time_difference' => $file1->created_at->diffForHumans($file2->created_at),
            'days_difference' => $file1->created_at->diffInDays($file2->created_at),
        ];

        // Perform file-type specific comparison
        if ($file1->file_type === $file2->file_type) {
            $typeSpecificDiff = $this->performTypeSpecificComparison($file1, $file2);
            $comparison['differences'] = array_merge($comparison['differences'], $typeSpecificDiff);
        }

        // Calculate basic similarity score based on file size
        $maxSize = max($file1->file_size, $file2->file_size);
        if ($maxSize > 0) {
            $comparison['similarity_score'] = 1 - ($sizeDiff / $maxSize);
        }

        return $comparison;
    }

    /**
     * Get detailed file information
     */
    private function getFileInfo(ProjectPcbFile $pcbFile): array
    {
        return [
            'id' => $pcbFile->id,
            'name' => $pcbFile->file_name,
            'version' => $pcbFile->version,
            'type' => $pcbFile->file_type,
            'size' => $pcbFile->file_size,
            'size_human' => $this->formatBytes($pcbFile->file_size),
            'hash' => $pcbFile->file_hash,
            'created_at' => $pcbFile->created_at,
            'uploaded_by' => $pcbFile->uploadedBy->name ?? 'Unknown',
            'description' => $pcbFile->description,
        ];
    }

    /**
     * Perform file-type specific comparison
     */
    private function performTypeSpecificComparison(ProjectPcbFile $file1, ProjectPcbFile $file2): array
    {
        $differences = [];

        switch ($file1->file_type) {
            case 'kicad':
                $differences = $this->compareKiCadFiles($file1, $file2);
                break;
            case 'altium':
                $differences = $this->compareAltiumFiles($file1, $file2);
                break;
            case 'gerber':
                $differences = $this->compareGerberFiles($file1, $file2);
                break;
            default:
                $differences[] = [
                    'type' => 'generic',
                    'message' => 'Generic file comparison - specific analysis not available for this file type',
                ];
        }

        return $differences;
    }

    /**
     * Compare KiCad files (basic text-based comparison)
     */
    private function compareKiCadFiles(ProjectPcbFile $file1, ProjectPcbFile $file2): array
    {
        $differences = [];

        try {
            $content1 = Storage::disk('syncthing')->get($file1->file_path);
            $content2 = Storage::disk('syncthing')->get($file2->file_path);

            // Count components (simple pattern matching)
            $components1 = $this->countKiCadComponents($content1);
            $components2 = $this->countKiCadComponents($content2);

            if ($components1 !== $components2) {
                $differences[] = [
                    'type' => 'component_count',
                    'old_count' => $components1,
                    'new_count' => $components2,
                    'change' => $components2 - $components1,
                ];
            }

            // Check for specific changes
            $changes = $this->detectKiCadChanges($content1, $content2);
            $differences = array_merge($differences, $changes);

        } catch (\Exception $e) {
            $differences[] = [
                'type' => 'error',
                'message' => 'Could not perform detailed KiCad comparison: ' . $e->getMessage(),
            ];
        }

        return $differences;
    }

    /**
     * Count components in KiCad file
     */
    private function countKiCadComponents(string $content): int
    {
        // Basic pattern for KiCad components
        preg_match_all('/\(module\s+/', $content, $matches);
        return count($matches[0]);
    }

    /**
     * Detect specific changes in KiCad files
     */
    private function detectKiCadChanges(string $content1, string $content2): array
    {
        $changes = [];

        // Check for layer changes
        $layers1 = $this->extractKiCadLayers($content1);
        $layers2 = $this->extractKiCadLayers($content2);

        if ($layers1 !== $layers2) {
            $changes[] = [
                'type' => 'layer_changes',
                'old_layers' => $layers1,
                'new_layers' => $layers2,
            ];
        }

        // Check for net changes
        $nets1 = $this->countKiCadNets($content1);
        $nets2 = $this->countKiCadNets($content2);

        if ($nets1 !== $nets2) {
            $changes[] = [
                'type' => 'net_count',
                'old_nets' => $nets1,
                'new_nets' => $nets2,
                'change' => $nets2 - $nets1,
            ];
        }

        return $changes;
    }

    /**
     * Extract layer information from KiCad file
     */
    private function extractKiCadLayers(string $content): array
    {
        preg_match_all('/\(\d+\s+([^)]+)\)/', $content, $matches);
        return array_unique($matches[1] ?? []);
    }

    /**
     * Count nets in KiCad file
     */
    private function countKiCadNets(string $content): int
    {
        preg_match_all('/\(net\s+\d+/', $content, $matches);
        return count($matches[0]);
    }

    /**
     * Compare Altium files (placeholder)
     */
    private function compareAltiumFiles(ProjectPcbFile $file1, ProjectPcbFile $file2): array
    {
        return [[
            'type' => 'altium_comparison',
            'message' => 'Altium file comparison not yet implemented - files are binary format',
        ]];
    }

    /**
     * Compare Gerber files (placeholder)
     */
    private function compareGerberFiles(ProjectPcbFile $file1, ProjectPcbFile $file2): array
    {
        return [[
            'type' => 'gerber_comparison',
            'message' => 'Gerber file comparison not yet implemented - would require specialized parser',
        ]];
    }

    /**
     * Get version history for a project's PCB files
     */
    public function getVersionHistory(Project $project, ?string $fileType = null): array
    {
        $query = ProjectPcbFile::where('project_id', $project->id)
            ->with('uploadedBy')
            ->orderByDesc('version');

        if ($fileType) {
            $query->where('file_type', $fileType);
        }

        $files = $query->get();

        $history = [];
        foreach ($files as $file) {
            $history[] = [
                'version' => $file->version,
                'file_name' => $file->file_name,
                'file_type' => $file->file_type,
                'size' => $this->formatBytes($file->file_size),
                'uploaded_at' => $file->created_at,
                'uploaded_by' => $file->uploadedBy->name ?? 'Unknown',
                'description' => $file->description,
                'download_url' => $this->getDownloadUrl($file),
            ];
        }

        return $history;
    }

    /**
     * Generate download URL for PCB file
     */
    private function getDownloadUrl(ProjectPcbFile $file): string
    {
        return route('projects.pcb.download', [
            'project' => $file->project_id,
            'file' => $file->id,
        ]);
    }

    /**
     * Format bytes to human readable size
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        
        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }

    /**
     * Create backup of current version before upload
     */
    public function createBackup(ProjectPcbFile $file): bool
    {
        try {
            $backupPath = "backups/pcb/{$file->project_id}/" . 
                         pathinfo($file->file_path, PATHINFO_FILENAME) . 
                         '_v' . $file->version . '_backup_' . now()->format('Y-m-d_H-i-s') . 
                         '.' . pathinfo($file->file_path, PATHINFO_EXTENSION);

            return Storage::disk('syncthing')->copy($file->file_path, $backupPath);
        } catch (\Exception $e) {
            \Log::error('PCB file backup failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get file differences for visualization
     */
    public function getVisualizationData(ProjectPcbFile $file): array
    {
        return [
            'file_id' => $file->id,
            'project_id' => $file->project_id,
            'file_type' => $file->file_type,
            'version' => $file->version,
            'preview_available' => $this->isPreviewAvailable($file),
            'preview_url' => $this->getPreviewUrl($file),
            'metadata' => $this->extractFileMetadata($file),
        ];
    }

    /**
     * Check if preview is available for file type
     */
    private function isPreviewAvailable(ProjectPcbFile $file): bool
    {
        return in_array($file->file_type, ['gerber', 'kicad']);
    }

    /**
     * Get preview URL (would integrate with gerber viewer)
     */
    private function getPreviewUrl(ProjectPcbFile $file): ?string
    {
        if (!$this->isPreviewAvailable($file)) {
            return null;
        }

        return route('projects.pcb.preview', [
            'project' => $file->project_id,
            'file' => $file->id,
        ]);
    }

    /**
     * Extract metadata from PCB file
     */
    private function extractFileMetadata(ProjectPcbFile $file): array
    {
        // This would extract specific metadata based on file type
        return [
            'layers' => $this->getLayerCount($file),
            'board_size' => $this->getBoardDimensions($file),
            'component_count' => $this->getComponentCount($file),
        ];
    }

    /**
     * Get layer count (placeholder)
     */
    private function getLayerCount(ProjectPcbFile $file): ?int
    {
        // Implementation would depend on file type
        return null;
    }

    /**
     * Get board dimensions (placeholder)
     */
    private function getBoardDimensions(ProjectPcbFile $file): ?array
    {
        // Implementation would parse PCB file for dimensions
        return null;
    }

    /**
     * Get component count (placeholder)
     */
    private function getComponentCount(ProjectPcbFile $file): ?int
    {
        // Implementation would count components in PCB file
        return null;
    }
}