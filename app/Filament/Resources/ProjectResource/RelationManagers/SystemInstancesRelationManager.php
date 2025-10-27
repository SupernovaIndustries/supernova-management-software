<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\SystemVariant;
use App\Models\Component;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class SystemInstancesRelationManager extends RelationManager
{
    protected static string $relationship = 'systemInstances';

    protected static ?string $title = 'Sistemi Progetto';

    protected static ?string $recordTitleAttribute = 'instance_name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('system_variant_id')
                    ->label('Variante Sistema')
                    ->options(function () {
                        return SystemVariant::with('category')
                            ->active()
                            ->get()
                            ->mapWithKeys(function ($variant) {
                                return [$variant->id => $variant->category->display_name . ' - ' . $variant->display_name];
                            });
                    })
                    ->required()
                    ->searchable()
                    ->reactive()
                    ->afterStateUpdated(function (Forms\Set $set, $state) {
                        if ($state) {
                            $variant = SystemVariant::with('category')->find($state);
                            if ($variant) {
                                $set('instance_name', $variant->category->display_name . ' - ' . $variant->display_name);
                            }
                        }
                    }),

                Forms\Components\TextInput::make('instance_name')
                    ->label('Nome Istanza')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Es: "Sistema IMU Principale", "GPS Backup"'),

                Forms\Components\Select::make('component_id')
                    ->label('Componente Associato (Opzionale)')
                    ->relationship('component', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Componente dalla BOM che ha triggerato questo sistema'),

                Forms\Components\Textarea::make('custom_notes')
                    ->label('Note Personalizzate')
                    ->rows(3)
                    ->columnSpanFull(),

                Forms\Components\KeyValue::make('custom_specifications')
                    ->label('Specifiche Personalizzate')
                    ->addActionLabel('Aggiungi Specifica')
                    ->keyLabel('Parametro')
                    ->valueLabel('Valore')
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_active')
                    ->label('Attivo')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('instance_name')
            ->columns([
                Tables\Columns\TextColumn::make('systemVariant.category.display_name')
                    ->label('Categoria')
                    ->badge()
                    ->color(fn ($record) => $record->systemVariant->category->color ?? 'primary'),

                Tables\Columns\TextColumn::make('systemVariant.display_name')
                    ->label('Variante')
                    ->searchable(),

                Tables\Columns\TextColumn::make('instance_name')
                    ->label('Nome Istanza')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('component.name')
                    ->label('Componente')
                    ->placeholder('—')
                    ->limit(30),

                Tables\Columns\TextColumn::make('completion_percentage')
                    ->label('Completamento')
                    ->formatStateUsing(fn ($state) => $state . '%')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state >= 90 => 'success',
                        $state >= 70 => 'info', 
                        $state >= 50 => 'warning',
                        default => 'danger',
                    }),

                Tables\Columns\IconColumn::make('critical_items_completed')
                    ->label('Critici OK')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('system_variant_id')
                    ->label('Variante Sistema')
                    ->relationship('systemVariant', 'display_name'),
                
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Solo Attivi'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Aggiungi Sistema')
                    ->after(function ($record) {
                        // Auto-genera checklist progress per questa istanza
                        $this->createChecklistProgress($record);
                        
                        Notification::make()
                            ->title('Sistema aggiunto')
                            ->body('Checklist di progettazione generata automaticamente')
                            ->success()
                            ->send();
                    }),
                    
                Tables\Actions\Action::make('auto_detect_systems')
                    ->label('Auto-Rileva da BOM')
                    ->icon('heroicon-o-cpu-chip')
                    ->color('info')
                    ->action(function () {
                        $this->autoDetectSystemsFromBom();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view_checklist')
                    ->label('Checklist')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->url(fn ($record) => route('filament.admin.resources.projects.system-checklist', [
                        'project' => $record->project_id,
                        'instance' => $record->id
                    ]))
                    ->openUrlInNewTab(),
                    
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Auto-genera checklist progress per una nuova istanza.
     */
    private function createChecklistProgress($instance)
    {
        $templates = $instance->systemVariant->checklistTemplates()
            ->with('checklistTemplateItems')
            ->get();

        foreach ($templates as $template) {
            foreach ($template->checklistTemplateItems as $item) {
                \App\Models\ProjectChecklistProgress::create([
                    'project_system_instance_id' => $instance->id,
                    'checklist_template_item_id' => $item->id,
                    'system_phase_id' => $template->system_phase_id,
                ]);
            }
        }
    }

    /**
     * Auto-rileva sistemi dalla BOM del progetto.
     */
    private function autoDetectSystemsFromBom()
    {
        $project = $this->ownerRecord;
        
        // Logica di auto-detection basata sui component mappings
        // TODO: Implementare logica di rilevamento automatico
        
        Notification::make()
            ->title('Auto-rilevamento')
            ->body('Funzionalità di auto-rilevamento in sviluppo')
            ->info()
            ->send();
    }
}