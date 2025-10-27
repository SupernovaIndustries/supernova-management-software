<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\ProjectDocument;
use App\Services\NextcloudService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;

class ProjectDocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'projectDocuments';

    protected static ?string $title = 'Documenti Progetto';

    protected static ?string $modelLabel = 'Documento';

    protected static ?string $pluralModelLabel = 'Documenti';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informazioni Documento')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Nome Documento'),

                        Forms\Components\Select::make('type')
                            ->options(ProjectDocument::getDocumentTypes())
                            ->required()
                            ->searchable()
                            ->label('Tipo Documento')
                            ->helperText('Seleziona il tipo di documento. Il file verrà automaticamente caricato nella cartella Nextcloud corretta.'),

                        Forms\Components\FileUpload::make('file_path')
                            ->label('File')
                            ->required()
                            ->preserveFilenames()
                            ->maxSize(102400) // 100 MB
                            ->acceptedFileTypes([
                                'application/pdf',
                                'image/*',
                                'application/zip',
                                'application/x-zip-compressed',
                                // 3D file formats
                                'model/stl',
                                'application/sla',
                                'application/vnd.ms-pki.stl',
                                'application/x-navistyle',
                                '.stl',
                                '.step',
                                '.stp',
                                '.iges',
                                '.igs',
                                '.obj',
                                '.3mf',
                                '.amf',
                                '.dxf',
                                '.dwg',
                                '.f3d',
                                '.ipt',
                                '.sldprt',
                                // CAD formats
                                'application/acad',
                                'application/x-acad',
                                'application/dxf',
                                'application/x-dwg',
                                'application/x-autocad',
                                'drawing/dwg',
                                'image/vnd.dwg',
                                'image/x-dwg',
                                // KiCad formats
                                '.kicad_pcb',
                                '.kicad_sch',
                                '.kicad_pro',
                                '.kicad_mod',
                                // Gerber
                                '.gbr',
                                '.gbl',
                                '.gtl',
                                '.gbs',
                                '.gts',
                                '.gbo',
                                '.gto',
                                '.gm1',
                                '.txt',
                                // Firmware
                                '.bin',
                                '.hex',
                                '.elf',
                            ])
                            ->helperText('Supporta: PDF, Immagini, ZIP, File 3D, CAD, KiCad, Gerber, Firmware. Max 100 MB.')
                            ->directory('project-documents')
                            ->visibility('private')
                            ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                if ($state) {
                                    $file = Storage::disk('local')->path($state);
                                    $filename = basename($file);
                                    $set('original_filename', $filename);
                                    $set('mime_type', Storage::disk('local')->mimeType($state));
                                    $set('file_size', Storage::disk('local')->size($state));

                                    // Auto-detect document type based on extension
                                    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                                    $type = $get('type');

                                    if (empty($type) || $type === 'other') {
                                        if (in_array($extension, ['stl', 'step', 'stp', 'iges', 'igs', 'obj', '3mf', 'amf'])) {
                                            $set('type', '3d_model');
                                        } elseif (in_array($extension, ['dxf', 'dwg', 'f3d'])) {
                                            $set('type', 'cad_drawing');
                                        } elseif (in_array($extension, ['kicad_pcb', 'kicad_sch', 'kicad_pro', 'kicad_mod'])) {
                                            $set('type', 'kicad_project');
                                        } elseif (in_array($extension, ['gbr', 'gbl', 'gtl', 'gbs', 'gts', 'gbo', 'gto', 'gm1'])) {
                                            $set('type', 'gerber');
                                        } elseif (in_array($extension, ['bin', 'hex', 'elf'])) {
                                            $set('type', 'firmware');
                                        }
                                    }
                                }
                            }),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Dettagli Aggiuntivi')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Descrizione')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\TagsInput::make('tags')
                            ->label('Tag')
                            ->separator(',')
                            ->placeholder('Aggiungi tag')
                            ->suggestions(['urgente', 'revisione', 'approvato', 'bozza', 'finale', 'v1', 'v2']),

                        Forms\Components\DatePicker::make('document_date')
                            ->label('Data Documento')
                            ->default(now()),

                        Forms\Components\TextInput::make('amount')
                            ->label('Importo')
                            ->numeric()
                            ->prefix('€')
                            ->step(0.01)
                            ->visible(fn (Get $get) => in_array($get('type'), ['invoice_received', 'invoice_issued'])),

                        Forms\Components\Select::make('currency')
                            ->label('Valuta')
                            ->options([
                                'EUR' => 'Euro (€)',
                                'USD' => 'Dollaro USA ($)',
                                'GBP' => 'Sterlina Britannica (£)',
                            ])
                            ->default('EUR')
                            ->visible(fn (Get $get) => in_array($get('type'), ['invoice_received', 'invoice_issued'])),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Attivo')
                            ->default(true),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome Documento')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->icon(fn ($record) => $record->getFileIcon())
                    ->iconPosition('before')
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'invoice_received', 'invoice_issued' => 'success',
                        'kicad_project', 'kicad_library', 'gerber', 'bom', 'bom_interactive' => 'primary',
                        '3d_model', '3d_case', '3d_mechanical', 'cad_drawing' => 'info',
                        'complaint', 'error_report' => 'danger',
                        'customs' => 'warning',
                        'certification', 'datasheet' => 'success',
                        'assembly_instructions', 'test_report' => 'secondary',
                        'firmware' => 'indigo',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string =>
                        ProjectDocument::getDocumentTypes()[$state] ?? $state
                    ),

                Tables\Columns\TextColumn::make('document_date')
                    ->label('Data')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('formatted_file_size')
                    ->label('Dimensione')
                    ->alignEnd(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Caricato')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo Documento')
                    ->options(ProjectDocument::getDocumentTypes())
                    ->multiple(),

                Tables\Filters\Filter::make('3d_files')
                    ->label('Solo File 3D/CAD')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereIn('type', ['3d_model', '3d_case', '3d_mechanical', 'cad_drawing'])
                    ),

                Tables\Filters\Filter::make('design_files')
                    ->label('Solo File Progettazione')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereIn('type', ['kicad_project', 'kicad_library', 'gerber', 'bom', 'bom_interactive'])
                    ),

                Tables\Filters\Filter::make('is_active')
                    ->label('Solo Attivi')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true))
                    ->default(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Carica Documento')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->modalHeading('Carica Nuovo Documento')
                    ->modalDescription('Il documento verrà automaticamente caricato su Nextcloud nella cartella corretta in base al tipo.')
                    ->modalWidth('3xl')
                    ->after(function ($record, NextcloudService $nextcloudService) {
                        // Upload to Nextcloud after creating the document
                        if ($record->file_path) {
                            try {
                                $localPath = Storage::disk('local')->path($record->file_path);

                                // Set the filename for Nextcloud (using original filename)
                                $tempDocument = clone $record;
                                $tempDocument->filename = $record->original_filename;

                                $uploaded = $nextcloudService->uploadProjectDocument($tempDocument, $localPath);

                                if ($uploaded) {
                                    Notification::make()
                                        ->success()
                                        ->title('Documento caricato con successo')
                                        ->body('Il documento è stato caricato su Nextcloud.')
                                        ->send();
                                } else {
                                    Notification::make()
                                        ->warning()
                                        ->title('Avviso')
                                        ->body('Documento salvato localmente, ma l\'upload su Nextcloud è fallito.')
                                        ->send();
                                }
                            } catch (\Exception $e) {
                                \Illuminate\Support\Facades\Log::error('Error uploading to Nextcloud: ' . $e->getMessage());

                                Notification::make()
                                    ->warning()
                                    ->title('Avviso Upload Nextcloud')
                                    ->body('Documento salvato localmente. Upload Nextcloud: ' . $e->getMessage())
                                    ->send();
                            }
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view_info')
                    ->label('Info')
                    ->icon('heroicon-o-information-circle')
                    ->color('info')
                    ->modalHeading('Informazioni Documento')
                    ->modalContent(fn (ProjectDocument $record) => view('filament.components.document-info', [
                        'record' => $record,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalWidth('2xl'),

                Tables\Actions\Action::make('download')
                    ->label('Scarica')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn (ProjectDocument $record): string => Storage::url($record->file_path))
                    ->openUrlInNewTab(),

                Tables\Actions\EditAction::make()
                    ->modalWidth('3xl'),

                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Elimina Documento')
                    ->modalDescription('Sei sicuro di voler eliminare questo documento? Questa azione non può essere annullata.')
                    ->after(function ($record) {
                        // Delete the file from storage
                        if ($record->file_path && Storage::disk('local')->exists($record->file_path)) {
                            Storage::disk('local')->delete($record->file_path);
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->after(function ($records) {
                            foreach ($records as $record) {
                                if ($record->file_path && Storage::disk('local')->exists($record->file_path)) {
                                    Storage::disk('local')->delete($record->file_path);
                                }
                            }
                        }),
                ]),
            ])
            ->emptyStateHeading('Nessun documento')
            ->emptyStateDescription('Carica il primo documento per questo progetto.')
            ->emptyStateIcon('heroicon-o-document')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Carica Primo Documento')
                    ->icon('heroicon-o-arrow-up-tray'),
            ]);
    }
}
