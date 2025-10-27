<?php

namespace App\Filament\Pages;

use App\Models\Project;
use App\Models\ProjectBom;
use App\Services\BomComparisonService;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;

class BomComparison extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?string $navigationLabel = 'BOM Comparison';
    protected static ?string $navigationGroup = 'Advanced Electronics';
    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.bom-comparison';

    public ?int $project_id = null;
    public ?int $bom1_id = null;
    public ?int $bom2_id = null;
    public array $comparisonData = [];
    public bool $showComparison = false;

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Grid::make()
                ->schema([
                    Forms\Components\Select::make('project_id')
                        ->label('Project')
                        ->options(Project::query()->pluck('name', 'id'))
                        ->searchable()
                        ->reactive()
                        ->afterStateUpdated(fn () => $this->reset(['bom1_id', 'bom2_id', 'comparisonData', 'showComparison'])),

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\Select::make('bom1_id')
                                ->label('BOM Version 1')
                                ->options(fn () => $this->project_id 
                                    ? ProjectBom::where('project_id', $this->project_id)
                                        ->get()
                                        ->mapWithKeys(fn ($bom) => [$bom->id => "Version {$bom->version} - {$bom->created_at->format('Y-m-d')}"])
                                    : [])
                                ->visible(fn () => $this->project_id)
                                ->reactive(),

                            Forms\Components\Select::make('bom2_id')
                                ->label('BOM Version 2')
                                ->options(fn () => $this->project_id 
                                    ? ProjectBom::where('project_id', $this->project_id)
                                        ->get()
                                        ->mapWithKeys(fn ($bom) => [$bom->id => "Version {$bom->version} - {$bom->created_at->format('Y-m-d')}"])
                                    : [])
                                ->visible(fn () => $this->project_id)
                                ->reactive()
                                ->different('bom1_id'),
                        ]),
                ])
        ];
    }

    public function compare(): void
    {
        if (!$this->bom1_id || !$this->bom2_id) {
            Notification::make()
                ->title('Selection Required')
                ->body('Please select two different BOM versions to compare')
                ->warning()
                ->send();
            return;
        }

        $bom1 = ProjectBom::find($this->bom1_id);
        $bom2 = ProjectBom::find($this->bom2_id);

        if (!$bom1 || !$bom2) {
            Notification::make()
                ->title('Error')
                ->body('Selected BOM versions not found')
                ->danger()
                ->send();
            return;
        }

        $service = app(BomComparisonService::class);
        $this->comparisonData = $service->compareBoms($bom1, $bom2);
        $this->showComparison = true;

        Notification::make()
            ->title('Comparison Complete')
            ->body('BOM comparison analysis is ready')
            ->success()
            ->send();
    }

    public function downloadReport(): void
    {
        // This would generate a PDF/Excel report of the comparison
        Notification::make()
            ->title('Report Generation')
            ->body('Comparison report download feature coming soon')
            ->info()
            ->send();
    }

    protected function getActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('compare')
                ->label('Compare BOMs')
                ->icon('heroicon-o-scale')
                ->visible(fn () => $this->bom1_id && $this->bom2_id)
                ->action('compare'),

            Forms\Components\Actions\Action::make('downloadReport')
                ->label('Download Report')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn () => $this->showComparison)
                ->color('success')
                ->action('downloadReport'),
        ];
    }

    public function getViewData(): array
    {
        return [
            'comparisonData' => $this->comparisonData,
            'showComparison' => $this->showComparison,
        ];
    }
}