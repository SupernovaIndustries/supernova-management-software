<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectPcbFileResource\Pages;
use App\Models\ProjectPcbFile;
use App\Services\PcbVersionControlService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class ProjectPcbFileResource extends Resource
{
    protected static ?string $model = ProjectPcbFile::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationLabel = 'PCB Version Control';

    protected static ?string $navigationGroup = 'Advanced Electronics';

    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('project_id')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label('Project'),
                    
                Forms\Components\FileUpload::make('file_upload')
                    ->label('PCB File')
                    ->required()
                    ->acceptedFileTypes([
                        'application/octet-stream', // Binary files
                        'text/plain', // Text files
                        '.kicad_pro', '.kicad_pcb', '.sch', // KiCad
                        '.PrjPCB', '.PcbDoc', '.SchDoc', // Altium
                        '.brd', '.sch', // Eagle
                        '.gbr', '.gbl', '.gbs', '.gbo', '.gts', '.gto', '.drl', // Gerber
                        '.zip', // Gerber packages
                    ])
                    ->maxSize(51200) // 50MB
                    ->directory('temp-pcb')
                    ->preserveFilenames()
                    ->downloadable(),
                    
                Forms\Components\Select::make('file_type')
                    ->options([
                        'kicad' => 'KiCad',
                        'altium' => 'Altium Designer',
                        'eagle' => 'Eagle',
                        'gerber' => 'Gerber Files',
                        'other' => 'Other',
                    ])
                    ->required()
                    ->reactive(),
                    
                Forms\Components\Textarea::make('description')
                    ->label('Version Description')
                    ->rows(3)
                    ->placeholder('Describe the changes in this version')
                    ->columnSpanFull(),
                    
                Forms\Components\Section::make('Version Information')
                    ->schema([
                        Forms\Components\TextInput::make('version')
                            ->label('Version Number')
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Auto-generated based on previous versions'),
                            
                        Forms\Components\TextInput::make('file_size')
                            ->label('File Size')
                            ->disabled()
                            ->dehydrated()
                            ->formatStateUsing(fn ($state) => $state ? number_format($state / 1024 / 1024, 2) . ' MB' : 'N/A'),
                            
                        Forms\Components\TextInput::make('file_hash')
                            ->label('File Hash (SHA-256)')
                            ->disabled()
                            ->dehydrated()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($livewire) => $livewire instanceof Pages\EditProjectPcbFile),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('project.name')
                    ->label('Project')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('file_name')
                    ->label('File Name')
                    ->searchable()
                    ->limit(30),
                    
                Tables\Columns\TextColumn::make('file_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'kicad' => 'success',
                        'altium' => 'info',
                        'eagle' => 'warning',
                        'gerber' => 'purple',
                        default => 'gray',
                    }),
                    
                Tables\Columns\TextColumn::make('version')
                    ->label('Version')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('file_size')
                    ->label('Size')
                    ->formatStateUsing(fn ($state) => number_format($state / 1024 / 1024, 2) . ' MB')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('uploadedBy.name')
                    ->label('Uploaded By')
                    ->placeholder('Unknown'),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Upload Date')
                    ->dateTime()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('project_id')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\SelectFilter::make('file_type')
                    ->options([
                        'kicad' => 'KiCad',
                        'altium' => 'Altium Designer',
                        'eagle' => 'Eagle',
                        'gerber' => 'Gerber Files',
                        'other' => 'Other',
                    ]),
                    
                Tables\Filters\Filter::make('latest_version')
                    ->label('Latest Version Only')
                    ->query(function (Builder $query) {
                        return $query->whereIn('id', function ($subquery) {
                            $subquery->select(\DB::raw('MAX(id)'))
                                ->from('project_pcb_files')
                                ->groupBy('project_id', 'file_type');
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (ProjectPcbFile $record) {
                        return Storage::disk('syncthing')->download($record->file_path, $record->file_name);
                    }),
                    
                Tables\Actions\Action::make('compare')
                    ->label('Compare')
                    ->icon('heroicon-o-arrows-right-left')
                    ->url(fn (ProjectPcbFile $record): string => 
                        route('filament.admin.pages.pcb-comparison', [
                            'project' => $record->project_id,
                            'file' => $record->id,
                        ])),
                        
                Tables\Actions\Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (ProjectPcbFile $record): bool => in_array($record->file_type, ['gerber', 'kicad']))
                    ->action(function (ProjectPcbFile $record) {
                        Notification::make()
                            ->title('PCB Preview')
                            ->body('PCB viewer integration coming soon for ' . $record->file_type . ' files')
                            ->info()
                            ->send();
                    }),
                    
                Tables\Actions\Action::make('create_backup')
                    ->label('Backup')
                    ->icon('heroicon-o-archive-box')
                    ->requiresConfirmation()
                    ->action(function (ProjectPcbFile $record) {
                        $service = app(PcbVersionControlService::class);
                        
                        if ($service->createBackup($record)) {
                            Notification::make()
                                ->title('Backup Created')
                                ->body('PCB file backup created successfully')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Backup Failed')
                                ->body('Failed to create backup')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
                Tables\Actions\Action::make('scan_syncthing')
                    ->label('Scan Syncthing Folders')
                    ->icon('heroicon-o-folder-open')
                    ->action(function () {
                        $service = app(\App\Services\PcbFileService::class);
                        
                        // This would scan all project folders for new PCB files
                        Notification::make()
                            ->title('Folder Scan')
                            ->body('Syncthing folder scanning feature coming soon')
                            ->info()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('create_backups')
                    ->label('Backup Selected')
                    ->icon('heroicon-o-archive-box')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $service = app(PcbVersionControlService::class);
                        $success = 0;
                        
                        foreach ($records as $record) {
                            if ($service->createBackup($record)) {
                                $success++;
                            }
                        }
                        
                        Notification::make()
                            ->title('Bulk Backup Complete')
                            ->body("{$success} files backed up successfully")
                            ->success()
                            ->send();
                    }),
                    
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->groups([
                Tables\Grouping\Group::make('project.name')
                    ->label('Project')
                    ->collapsible(),
                Tables\Grouping\Group::make('file_type')
                    ->label('File Type')
                    ->collapsible(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjectPcbFiles::route('/'),
            'create' => Pages\CreateProjectPcbFile::route('/create'),
            'edit' => Pages\EditProjectPcbFile::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        // Show count of files uploaded today
        return static::getModel()::whereDate('created_at', today())->count() ?: null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['project', 'uploadedBy']);
    }
}