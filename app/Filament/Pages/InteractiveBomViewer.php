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
use App\Models\Project;
use App\Models\ProjectBom;
use App\Services\InteractiveBomService;
use Illuminate\Support\Facades\Storage;

class InteractiveBomViewer extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';
    protected static ?string $navigationGroup = 'Project Management';
    protected static ?string $navigationLabel = 'Interactive BOM';
    protected static ?int $navigationSort = 5;
    protected static string $view = 'filament.pages.interactive-bom-viewer';

    public ?string $project_id = null;
    public ?string $bom_id = null;
    public ?string $ibomUrl = null;
    public ?ProjectBom $currentBom = null;

    public function mount(): void
    {
        // Check if we have parameters from route
        if (request()->has('project') && request()->has('bom')) {
            $this->project_id = request()->get('project');
            $this->bom_id = request()->get('bom');
            $this->loadBom();
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Select Project BOM')
                    ->schema([
                        Select::make('project_id')
                            ->label('Project')
                            ->options(Project::pluck('name', 'id'))
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state) {
                                $this->bom_id = null;
                                $this->ibomUrl = null;
                                $this->currentBom = null;
                            }),
                        
                        Select::make('bom_id')
                            ->label('BOM Version')
                            ->options(function () {
                                if (!$this->project_id) {
                                    return [];
                                }
                                
                                return ProjectBom::where('project_id', $this->project_id)
                                    ->orderBy('version', 'desc')
                                    ->get()
                                    ->mapWithKeys(function ($bom) {
                                        $label = 'v' . $bom->version . ' - ' . $bom->created_at->format('Y-m-d');
                                        if ($bom->is_approved) {
                                            $label .= ' (Approved)';
                                        }
                                        return [$bom->id => $label];
                                    });
                            })
                            ->required()
                            ->visible(fn () => $this->project_id !== null),
                    ])
                    ->columns(2),
            ]);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('generate')
                ->label('Generate Interactive BOM')
                ->icon('heroicon-o-sparkles')
                ->disabled(fn () => !$this->project_id || !$this->bom_id)
                ->action('generateIBom'),
                
            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->disabled(fn () => !$this->currentBom)
                ->action('generateIBom'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label('Download HTML')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn () => $this->ibomUrl !== null)
                ->action(function () {
                    return response()->download(Storage::disk('public')->path($this->ibomUrl));
                }),
                
            Action::make('open_new_tab')
                ->label('Open in New Tab')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->visible(fn () => $this->ibomUrl !== null)
                ->url(fn () => Storage::disk('public')->url($this->ibomUrl))
                ->openUrlInNewTab(),
        ];
    }

    public function loadBom(): void
    {
        if ($this->bom_id) {
            $this->currentBom = ProjectBom::find($this->bom_id);
            
            // Check if iBOM already exists
            $existingFiles = Storage::disk('public')->files('ibom/' . $this->project_id);
            foreach ($existingFiles as $file) {
                if (str_contains($file, 'ibom-v' . $this->currentBom->version)) {
                    $this->ibomUrl = $file;
                    break;
                }
            }
        }
    }

    public function generateIBom(): void
    {
        if (!$this->project_id || !$this->bom_id) {
            return;
        }

        $project = Project::findOrFail($this->project_id);
        $bom = ProjectBom::findOrFail($this->bom_id);
        
        $service = app(InteractiveBomService::class);
        
        try {
            $this->ibomUrl = $service->saveInteractiveBom($project, $bom);
            $this->currentBom = $bom;
            
            Notification::make()
                ->title('Interactive BOM Generated')
                ->body('The interactive BOM has been generated successfully.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Generation Failed')
                ->body('Failed to generate interactive BOM: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getViewData(): array
    {
        return [
            'ibomUrl' => $this->ibomUrl ? Storage::disk('public')->url($this->ibomUrl) : null,
            'currentBom' => $this->currentBom,
        ];
    }
}