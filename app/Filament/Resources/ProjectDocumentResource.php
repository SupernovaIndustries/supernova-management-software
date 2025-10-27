<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectDocumentResource\Pages;
use App\Filament\Resources\ProjectDocumentResource\RelationManagers;
use App\Models\ProjectDocument;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;

class ProjectDocumentResource extends Resource
{
    protected static ?string $model = ProjectDocument::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    
    protected static ?string $navigationLabel = 'Documenti Progetto';
    
    protected static ?string $modelLabel = 'Documento Progetto';
    
    protected static ?string $pluralModelLabel = 'Documenti Progetto';
    
    protected static ?string $navigationGroup = 'Gestione Progetti';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informazioni Documento')
                    ->schema([
                        Forms\Components\Select::make('project_id')
                            ->relationship('project', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->label('Progetto'),
                            
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Nome Documento'),
                            
                        Forms\Components\Select::make('type')
                            ->options(ProjectDocument::getDocumentTypes())
                            ->required()
                            ->searchable()
                            ->label('Tipo Documento'),
                            
                        Forms\Components\FileUpload::make('file_path')
                            ->label('File')
                            ->required()
                            ->preserveFilenames()
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
                            ])
                            ->helperText('Supporta: PDF, Immagini, ZIP, File 3D (STL, STEP, IGES, OBJ, 3MF, AMF), CAD (DXF, DWG, F3D)')
                            ->directory('project-documents')
                            ->visibility('private')
                            ->afterStateUpdated(function (Set $set, $state, Get $get) {
                                if ($state) {
                                    $file = Storage::disk('local')->path($state);
                                    $filename = basename($file);
                                    $set('original_filename', $filename);
                                    $set('mime_type', Storage::disk('local')->mimeType($state));
                                    $set('file_size', Storage::disk('local')->size($state));
                                    
                                    // Auto-detect 3D file type based on extension
                                    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                                    $type = $get('type');
                                    
                                    if (empty($type) || $type === 'other') {
                                        if (in_array($extension, ['stl', 'step', 'stp', 'iges', 'igs', 'obj', '3mf', 'amf'])) {
                                            $set('type', '3d_model');
                                        } elseif (in_array($extension, ['dxf', 'dwg', 'f3d'])) {
                                            $set('type', 'cad_drawing');
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
                            ->suggestions(['urgente', 'revisione', 'approvato', 'bozza', 'finale']),
                            
                        Forms\Components\DatePicker::make('document_date')
                            ->label('Data Documento'),
                            
                        Forms\Components\TextInput::make('amount')
                            ->label('Importo')
                            ->numeric()
                            ->prefix('€')
                            ->step(0.01)
                            ->visible(fn (Get $get) => in_array($get('type'), ['invoice_received', 'invoice_issued'])),
                            
                        Forms\Components\Select::make('currency')
                            ->label('Valuta')
                            ->options([
                                'EUR' => 'Euro',
                                'USD' => 'Dollaro USA',
                                'GBP' => 'Sterlina Britannica',
                            ])
                            ->default('EUR')
                            ->visible(fn (Get $get) => in_array($get('type'), ['invoice_received', 'invoice_issued'])),
                            
                        Forms\Components\Toggle::make('is_active')
                            ->label('Attivo')
                            ->default(true),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Informazioni File')
                    ->schema([
                        Forms\Components\TextInput::make('original_filename')
                            ->label('Nome File Originale')
                            ->disabled()
                            ->dehydrated(false),
                            
                        Forms\Components\TextInput::make('mime_type')
                            ->label('Tipo MIME')
                            ->disabled()
                            ->dehydrated(false),
                            
                        Forms\Components\TextInput::make('file_size')
                            ->label('Dimensione File (bytes)')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(3)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('project.name')
                    ->label('Progetto')
                    ->searchable()
                    ->sortable()
                    ->limit(25),
                    
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome Documento')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->icon(fn ($record) => $record->getFileIcon())
                    ->iconPosition('before'),
                    
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'invoice_received', 'invoice_issued' => 'success',
                        'kicad_project', 'gerber', 'bom' => 'primary',
                        '3d_model', '3d_case', '3d_mechanical', 'cad_drawing' => 'info',
                        'complaint', 'error_report' => 'danger',
                        'customs' => 'warning',
                        'certification', 'datasheet' => 'success',
                        'assembly_instructions', 'test_report' => 'secondary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => 
                        ProjectDocument::getDocumentTypes()[$state] ?? $state
                    ),
                    
                Tables\Columns\TextColumn::make('document_date')
                    ->label('Data Doc.')
                    ->date()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('amount')
                    ->label('Importo')
                    ->money('EUR')
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('formatted_file_size')
                    ->label('Dimensione')
                    ->toggleable(),
                    
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean()
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Caricato')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('project_id')
                    ->label('Progetto')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo Documento')
                    ->options(ProjectDocument::getDocumentTypes())
                    ->multiple(),
                    
                Tables\Filters\Filter::make('3d_files')
                    ->label('Solo File 3D')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereIn('type', ['3d_model', '3d_case', '3d_mechanical', 'cad_drawing'])
                    ),
                    
                Tables\Filters\Filter::make('is_active')
                    ->label('Solo Attivi')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true)),
            ])
            ->actions([
                Tables\Actions\Action::make('view_3d')
                    ->label('Info 3D')
                    ->icon('heroicon-o-cube')
                    ->color('info')
                    ->modalHeading('Informazioni File 3D')
                    ->modalContent(function (ProjectDocument $record) {
                        $extension = strtolower(pathinfo($record->original_filename, PATHINFO_EXTENSION));
                        $info = match($extension) {
                            'stl' => 'File STL (Stereolithography) - Formato standard per stampa 3D',
                            'step', 'stp' => 'File STEP (Standard Exchange Product) - Formato CAD universale',
                            'iges', 'igs' => 'File IGES (Initial Graphics Exchange Specification)',
                            'obj' => 'File OBJ (Wavefront) - Formato 3D con texture',
                            '3mf' => 'File 3MF (3D Manufacturing Format) - Formato moderno per stampa 3D',
                            'amf' => 'File AMF (Additive Manufacturing File) - Formato avanzato per stampa 3D',
                            'dxf' => 'File DXF (Drawing Exchange Format) - Formato CAD 2D/3D Autodesk',
                            'dwg' => 'File DWG (Drawing) - Formato nativo AutoCAD',
                            'f3d' => 'File F3D - Formato Fusion 360',
                            default => 'Formato 3D/CAD'
                        };
                        
                        return view('filament.components.3d-file-info', [
                            'record' => $record,
                            'format_info' => $info,
                            'extension' => $extension
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->visible(fn (ProjectDocument $record) => $record->is3DFile() || $record->isCadFile()),
                    
                Tables\Actions\Action::make('download')
                    ->label('Scarica')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (ProjectDocument $record): string => Storage::url($record->file_path))
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjectDocuments::route('/'),
            'create' => Pages\CreateProjectDocument::route('/create'),
            'edit' => Pages\EditProjectDocument::route('/{record}/edit'),
        ];
    }
}
