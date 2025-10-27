<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Models\ProjectPcbFile;
use App\Models\Project;
use App\Services\PcbVersionControlService;
use Illuminate\Support\HtmlString;

class PcbComparison extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = 'Electronics Management';
    protected static ?string $navigationLabel = 'PCB Comparison';
    protected static ?int $navigationSort = 4;
    protected static string $view = 'filament.pages.pcb-comparison';

    public ?string $project_id = null;
    public ?string $file1_id = null;
    public ?string $file2_id = null;
    public array $comparisonData = [];
    public array $visualComparison = [];

    public function mount(): void
    {
        // Check if we have file IDs from route parameters
        if (request()->has('file1') && request()->has('file2')) {
            $this->file1_id = request()->get('file1');
            $this->file2_id = request()->get('file2');
            
            // Get project ID from first file
            $file1 = ProjectPcbFile::find($this->file1_id);
            if ($file1) {
                $this->project_id = $file1->project_id;
            }
            
            // Automatically run comparison
            $this->compare();
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Select PCB Files to Compare')
                    ->schema([
                        Select::make('project_id')
                            ->label('Project')
                            ->options(Project::pluck('name', 'id'))
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state) {
                                $this->file1_id = null;
                                $this->file2_id = null;
                                $this->comparisonData = [];
                            }),
                        
                        Select::make('file1_id')
                            ->label('First PCB File')
                            ->options(function () {
                                if (!$this->project_id) {
                                    return [];
                                }
                                
                                return ProjectPcbFile::where('project_id', $this->project_id)
                                    ->orderBy('version', 'desc')
                                    ->get()
                                    ->mapWithKeys(function ($file) {
                                        return [$file->id => $file->version . ' - ' . $file->filename . ($file->is_primary ? ' (Primary)' : '')];
                                    });
                            })
                            ->required()
                            ->visible(fn () => $this->project_id !== null),
                        
                        Select::make('file2_id')
                            ->label('Second PCB File')
                            ->options(function () {
                                if (!$this->project_id) {
                                    return [];
                                }
                                
                                return ProjectPcbFile::where('project_id', $this->project_id)
                                    ->orderBy('version', 'desc')
                                    ->get()
                                    ->mapWithKeys(function ($file) {
                                        return [$file->id => $file->version . ' - ' . $file->filename . ($file->is_primary ? ' (Primary)' : '')];
                                    });
                            })
                            ->required()
                            ->visible(fn () => $this->project_id !== null)
                            ->different('file1_id'),
                    ])
                    ->columns(3),
            ]);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('compare')
                ->label('Compare Files')
                ->icon('heroicon-o-arrows-right-left')
                ->disabled(fn () => !$this->file1_id || !$this->file2_id)
                ->action('compare'),
        ];
    }

    public function compare(): void
    {
        if (!$this->file1_id || !$this->file2_id) {
            return;
        }

        $service = app(PcbVersionControlService::class);
        
        $file1 = ProjectPcbFile::findOrFail($this->file1_id);
        $file2 = ProjectPcbFile::findOrFail($this->file2_id);
        
        // Basic comparison data
        $this->comparisonData = [
            'file1' => [
                'version' => $file1->version,
                'filename' => $file1->filename,
                'format' => $file1->format,
                'file_size' => $file1->file_size,
                'created_at' => $file1->created_at,
                'uploaded_by' => $file1->uploadedBy->name,
                'change_type' => $file1->change_type,
                'change_description' => $file1->change_description,
                'drc_results' => json_decode($file1->drc_results, true),
            ],
            'file2' => [
                'version' => $file2->version,
                'filename' => $file2->filename,
                'format' => $file2->format,
                'file_size' => $file2->file_size,
                'created_at' => $file2->created_at,
                'uploaded_by' => $file2->uploadedBy->name,
                'change_type' => $file2->change_type,
                'change_description' => $file2->change_description,
                'drc_results' => json_decode($file2->drc_results, true),
            ],
            'differences' => [],
        ];
        
        // Calculate differences
        $sizeDiff = $file2->file_size - $file1->file_size;
        $this->comparisonData['differences']['size'] = [
            'value' => abs($sizeDiff),
            'percentage' => $file1->file_size > 0 ? round(($sizeDiff / $file1->file_size) * 100, 2) : 0,
            'direction' => $sizeDiff > 0 ? 'increased' : ($sizeDiff < 0 ? 'decreased' : 'unchanged'),
        ];
        
        // Compare DRC results
        $drc1 = $this->comparisonData['file1']['drc_results'] ?? [];
        $drc2 = $this->comparisonData['file2']['drc_results'] ?? [];
        
        $this->comparisonData['differences']['drc'] = [
            'errors' => [
                'file1' => $drc1['errors'] ?? 0,
                'file2' => $drc2['errors'] ?? 0,
                'diff' => ($drc2['errors'] ?? 0) - ($drc1['errors'] ?? 0),
            ],
            'warnings' => [
                'file1' => $drc1['warnings'] ?? 0,
                'file2' => $drc2['warnings'] ?? 0,
                'diff' => ($drc2['warnings'] ?? 0) - ($drc1['warnings'] ?? 0),
            ],
        ];
        
        // Version comparison
        $this->comparisonData['differences']['version'] = [
            'comparison' => version_compare($file1->version, $file2->version),
            'is_newer' => version_compare($file2->version, $file1->version) > 0,
        ];
        
        // If both files are the same format and are text-based (like KiCad), try to get visual differences
        if ($file1->format === $file2->format && in_array($file1->format, ['kicad', 'eagle'])) {
            // This would integrate with actual PCB diff tools
            $this->visualComparison = [
                'available' => true,
                'format' => $file1->format,
                'message' => 'Visual comparison available for ' . strtoupper($file1->format) . ' files',
            ];
        } else {
            $this->visualComparison = [
                'available' => false,
                'message' => 'Visual comparison not available for different formats or binary files',
            ];
        }
        
        Notification::make()
            ->title('Comparison Complete')
            ->body('PCB files compared successfully')
            ->success()
            ->send();
    }

    protected function getViewData(): array
    {
        return [
            'comparisonData' => $this->comparisonData,
            'visualComparison' => $this->visualComparison,
        ];
    }
}