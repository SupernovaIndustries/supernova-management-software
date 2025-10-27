<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class ProjectFileService
{
    private const KICAD_FILE_TYPES = [
        'kicad_pro' => 'KiCad Project',
        'kicad_sch' => 'KiCad Schematic', 
        'kicad_pcb' => 'KiCad PCB',
        'kicad_lib' => 'KiCad Library',
        'kicad_sym' => 'KiCad Symbol Library',
        'kicad_mod' => 'KiCad Footprint Library',
        'kicad_wks' => 'KiCad Worksheet',
    ];

    private const GERBER_EXTENSIONS = [
        'gbr', 'gtl', 'gts', 'gto', 'gtp', 'gbl', 'gbs', 'gbo', 'gbp',
        'g1', 'g2', 'g3', 'g4', 'gm1', 'gm2', 'gm3', 'gko', 'drl', 'xln'
    ];

    /**
     * Upload and organize KiCad project files.
     */
    public function uploadKicadProject(Project $project, UploadedFile $file): array
    {
        $results = [];
        
        if ($file->getClientOriginalExtension() === 'zip') {
            $results = $this->extractAndProcessKicadZip($project, $file);
        } else {
            $results[] = $this->processKicadFile($project, $file);
        }

        return $results;
    }

    /**
     * Extract and process KiCad ZIP file.
     */
    private function extractAndProcessKicadZip(Project $project, UploadedFile $file): array
    {
        $results = [];
        $tempPath = $file->store('temp');
        $extractPath = storage_path('app/temp/kicad_extract_' . Str::random(8));
        
        $zip = new ZipArchive;
        if ($zip->open(Storage::path($tempPath)) === TRUE) {
            $zip->extractTo($extractPath);
            $zip->close();

            // Process extracted files
            $files = $this->getFilesRecursively($extractPath);
            
            foreach ($files as $filePath) {
                $fileName = basename($filePath);
                $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
                if ($this->isKicadFile($extension) || $this->isGerberFile($extension)) {
                    $uploadedFile = new \Illuminate\Http\UploadedFile(
                        $filePath,
                        $fileName,
                        mime_content_type($filePath),
                        null,
                        true
                    );
                    
                    $results[] = $this->processKicadFile($project, $uploadedFile);
                }
            }

            // Cleanup
            $this->deleteDirectory($extractPath);
        }

        Storage::delete($tempPath);
        return $results;
    }

    /**
     * Process individual KiCad file.
     */
    private function processKicadFile(Project $project, UploadedFile $file): ProjectDocument
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $fileName = $file->getClientOriginalName();
        
        // Determine document type
        $documentType = $this->determineDocumentType($extension);
        
        // Store file in organized structure
        $filePath = $file->store("projects/{$project->code}/kicad", 'local');
        
        // Create document record
        return ProjectDocument::create([
            'project_id' => $project->id,
            'name' => pathinfo($fileName, PATHINFO_FILENAME),
            'type' => $documentType,
            'file_path' => $filePath,
            'original_filename' => $fileName,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'description' => $this->generateFileDescription($extension, $fileName),
            'tags' => [$this->getFileCategory($extension)],
            'document_date' => now(),
        ]);
    }

    /**
     * Upload Gerber files.
     */
    public function uploadGerberFiles(Project $project, array $files): array
    {
        $results = [];
        
        foreach ($files as $file) {
            $extension = strtolower($file->getClientOriginalExtension());
            
            if (!$this->isGerberFile($extension)) {
                continue;
            }
            
            $filePath = $file->store("projects/{$project->code}/gerber", 'local');
            
            $results[] = ProjectDocument::create([
                'project_id' => $project->id,
                'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'type' => 'gerber',
                'file_path' => $filePath,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'description' => $this->getGerberDescription($extension),
                'tags' => ['gerber', 'manufacturing'],
                'document_date' => now(),
            ]);
        }
        
        return $results;
    }

    /**
     * Upload BOM file.
     */
    public function uploadBomFile(Project $project, UploadedFile $file, bool $isInteractive = false): ProjectDocument
    {
        $documentType = $isInteractive ? 'bom_interactive' : 'bom';
        $subfolder = $isInteractive ? 'bom_interactive' : 'bom';
        
        $filePath = $file->store("projects/{$project->code}/{$subfolder}", 'local');
        
        return ProjectDocument::create([
            'project_id' => $project->id,
            'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'type' => $documentType,
            'file_path' => $filePath,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'description' => $isInteractive ? 'Interactive Bill of Materials' : 'Bill of Materials',
            'tags' => ['bom', 'manufacturing'],
            'document_date' => now(),
        ]);
    }

    /**
     * Get project file summary.
     */
    public function getProjectFileSummary(Project $project): array
    {
        $documents = $project->projectDocuments()->get();
        
        $summary = [
            'total_files' => $documents->count(),
            'total_size' => $documents->sum('file_size'),
            'by_type' => [],
            'kicad_complete' => false,
            'has_gerber' => false,
            'has_bom' => false,
        ];

        foreach ($documents as $doc) {
            $summary['by_type'][$doc->type] = ($summary['by_type'][$doc->type] ?? 0) + 1;
        }

        // Check completeness
        $kicadTypes = array_keys(array_filter($summary['by_type'], fn($type) => str_contains($type, 'kicad')));
        $summary['kicad_complete'] = count($kicadTypes) >= 3; // project, schematic, pcb
        $summary['has_gerber'] = isset($summary['by_type']['gerber']);
        $summary['has_bom'] = isset($summary['by_type']['bom']) || isset($summary['by_type']['bom_interactive']);

        return $summary;
    }

    /**
     * Organize project files into proper directory structure.
     */
    public function organizeProjectFiles(Project $project): void
    {
        $documents = $project->projectDocuments()->get();
        
        foreach ($documents as $document) {
            $currentPath = $document->file_path;
            $targetDirectory = $this->getTargetDirectory($project, $document->type);
            $targetPath = "{$targetDirectory}/" . basename($currentPath);
            
            if (Storage::exists($currentPath) && $currentPath !== $targetPath) {
                Storage::move($currentPath, $targetPath);
                $document->update(['file_path' => $targetPath]);
            }
        }
    }

    // Helper methods
    
    private function isKicadFile(string $extension): bool
    {
        return in_array($extension, ['pro', 'sch', 'pcb', 'lib', 'dcm', 'sym', 'mod', 'wks', 'kicad_pro', 'kicad_sch', 'kicad_pcb']);
    }

    private function isGerberFile(string $extension): bool
    {
        return in_array($extension, self::GERBER_EXTENSIONS);
    }

    private function determineDocumentType(string $extension): string
    {
        return match($extension) {
            'pro', 'kicad_pro' => 'kicad_project',
            'sch', 'kicad_sch' => 'kicad_project',
            'pcb', 'kicad_pcb' => 'kicad_project',
            'lib', 'dcm', 'sym', 'mod', 'kicad_sym', 'kicad_mod' => 'kicad_library',
            default => $this->isGerberFile($extension) ? 'gerber' : 'other'
        };
    }

    private function generateFileDescription(string $extension, string $fileName): string
    {
        return match($extension) {
            'pro', 'kicad_pro' => 'KiCad Project File',
            'sch', 'kicad_sch' => 'KiCad Schematic File',
            'pcb', 'kicad_pcb' => 'KiCad PCB Layout File',
            'lib', 'kicad_lib' => 'KiCad Component Library',
            'sym', 'kicad_sym' => 'KiCad Symbol Library',
            'mod', 'kicad_mod' => 'KiCad Footprint Library',
            default => "Project file: {$fileName}"
        };
    }

    private function getFileCategory(string $extension): string
    {
        if ($this->isKicadFile($extension)) return 'kicad';
        if ($this->isGerberFile($extension)) return 'gerber';
        return 'other';
    }

    private function getGerberDescription(string $extension): string
    {
        return match($extension) {
            'gtl' => 'Gerber Top Layer',
            'gbl' => 'Gerber Bottom Layer',
            'gts' => 'Gerber Top Solder Mask',
            'gbs' => 'Gerber Bottom Solder Mask',
            'gto' => 'Gerber Top Overlay',
            'gbo' => 'Gerber Bottom Overlay',
            'gko' => 'Gerber Keep Out Layer',
            'drl' => 'Drill File',
            default => 'Gerber Manufacturing File'
        };
    }

    private function getTargetDirectory(Project $project, string $type): string
    {
        return match($type) {
            'kicad_project', 'kicad_library' => "projects/{$project->code}/kicad",
            'gerber' => "projects/{$project->code}/gerber",
            'bom' => "projects/{$project->code}/bom",
            'bom_interactive' => "projects/{$project->code}/bom_interactive",
            default => "projects/{$project->code}/documents"
        };
    }

    private function getFilesRecursively(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }

    private function deleteDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            $files = array_diff(scandir($directory), ['.', '..']);
            foreach ($files as $file) {
                $path = $directory . DIRECTORY_SEPARATOR . $file;
                is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
            }
            rmdir($directory);
        }
    }
}