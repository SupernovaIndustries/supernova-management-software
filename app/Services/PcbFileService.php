<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectPcbFile;
use Illuminate\Support\Facades\Log;

class PcbFileService
{
    protected array $kicadExtensions = [
        '.kicad_pcb', '.kicad_pro', '.kicad_sch', '.sch', '.pro', '.pcb'
    ];

    protected array $gerberExtensions = [
        '.gbr', '.gbl', '.gbo', '.gbs', '.gko', '.gtl', '.gto', '.gts',
        '.drl', '.txt', '.zip'
    ];

    protected array $altiumExtensions = [
        '.pcbdoc', '.schdoc', '.prjpcb'
    ];

    protected array $eagleExtensions = [
        '.brd', '.sch', '.lbr'
    ];

    /**
     * Scan project folder for PCB files
     */
    public function scanProjectPcbFiles(Project $project): array
    {
        $results = [
            'found' => 0,
            'imported' => 0,
            'errors' => [],
        ];

        if (!$project->customer || !$project->customer->folder) {
            $results['errors'][] = 'Project customer folder not set';
            return $results;
        }

        try {
            $disk = app('syncthing.paths')->disk('clients');
            $projectPath = $project->customer->folder . '/PCB';

            if (!$disk->exists($projectPath)) {
                $results['errors'][] = "PCB folder not found: {$projectPath}";
                return $results;
            }

            // Scan all files recursively
            $files = $disk->allFiles($projectPath);

            foreach ($files as $file) {
                $fileType = $this->detectFileType($file);
                
                if ($fileType) {
                    $results['found']++;
                    
                    if ($this->importPcbFile($project, $file, $fileType)) {
                        $results['imported']++;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('PCB file scan failed', [
                'project' => $project->code,
                'error' => $e->getMessage()
            ]);
            $results['errors'][] = 'Scan failed: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Detect file type based on extension
     */
    protected function detectFileType(string $filePath): ?string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $fileName = basename($filePath);

        // Check specific extensions
        foreach ($this->kicadExtensions as $ext) {
            if (str_ends_with($fileName, $ext)) {
                return 'kicad';
            }
        }

        foreach ($this->gerberExtensions as $ext) {
            if (str_ends_with($fileName, $ext)) {
                return 'gerber';
            }
        }

        foreach ($this->altiumExtensions as $ext) {
            if (str_ends_with($fileName, $ext)) {
                return 'altium';
            }
        }

        foreach ($this->eagleExtensions as $ext) {
            if (str_ends_with($fileName, $ext)) {
                return 'eagle';
            }
        }

        return null;
    }

    /**
     * Import PCB file to database
     */
    protected function importPcbFile(Project $project, string $filePath, string $fileType): bool
    {
        try {
            $fileName = basename($filePath);
            $folderPath = dirname($filePath);
            $version = $this->extractVersion($fileName);

            ProjectPcbFile::updateOrCreate(
                [
                    'project_id' => $project->id,
                    'file_name' => $fileName,
                ],
                [
                    'file_type' => $fileType,
                    'folder_path' => $folderPath,
                    'version' => $version,
                    'metadata' => $this->extractMetadata($filePath, $fileType),
                ]
            );

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to import PCB file', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Extract version from filename
     */
    protected function extractVersion(string $fileName): ?string
    {
        // Match patterns like v1, v2, v1.0, v2.1, etc.
        if (preg_match('/v(\d+(?:\.\d+)?)/i', $fileName, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract metadata from file
     */
    protected function extractMetadata(string $filePath, string $fileType): array
    {
        $metadata = [
            'file_type' => $fileType,
            'last_modified' => null,
            'size' => null,
        ];

        try {
            $disk = app('syncthing.paths')->disk('clients');
            
            if ($disk->exists($filePath)) {
                $metadata['last_modified'] = $disk->lastModified($filePath);
                $metadata['size'] = $disk->size($filePath);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to extract file metadata', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
        }

        return $metadata;
    }

    /**
     * Find BOM file in project folder
     */
    public function findProjectBomFile(Project $project): ?string
    {
        if (!$project->customer || !$project->customer->folder) {
            return null;
        }

        try {
            $disk = app('syncthing.paths')->disk('clients');
            
            // Common BOM locations
            $bomPaths = [
                $project->customer->folder . '/Prototipi/v1/Bom',
                $project->customer->folder . '/PCB/Bom',
                $project->customer->folder . '/Bom',
                $project->customer->folder . '/BOM',
            ];

            foreach ($bomPaths as $path) {
                if ($disk->exists($path)) {
                    // Look for CSV files
                    $files = $disk->files($path);
                    
                    foreach ($files as $file) {
                        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'csv') {
                            return $file;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to find BOM file', [
                'project' => $project->code,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }
}