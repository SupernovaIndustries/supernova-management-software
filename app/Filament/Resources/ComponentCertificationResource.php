<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComponentCertificationResource\Pages;
use App\Models\ComponentCertification;
use App\Services\CertificationManagementService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class ComponentCertificationResource extends Resource
{
    protected static ?string $model = ComponentCertification::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Component Certifications';

    protected static ?string $navigationGroup = 'Advanced Electronics';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('component_id')
                    ->relationship('component', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label('Component'),
                    
                Forms\Components\Select::make('certification_type')
                    ->options(ComponentCertification::getCeRelevantTypes())
                    ->required()
                    ->searchable(),
                    
                Forms\Components\TextInput::make('certificate_number')
                    ->label('Certificate Number')
                    ->maxLength(255),
                    
                Forms\Components\TextInput::make('issuing_authority')
                    ->label('Issuing Authority')
                    ->maxLength(255),
                    
                Forms\Components\DatePicker::make('issue_date')
                    ->label('Issue Date'),
                    
                Forms\Components\DatePicker::make('expiry_date')
                    ->label('Expiry Date')
                    ->afterOrEqual('issue_date'),
                    
                Forms\Components\Select::make('status')
                    ->options([
                        'valid' => 'Valid',
                        'pending' => 'Pending',
                        'expired' => 'Expired',
                        'revoked' => 'Revoked',
                    ])
                    ->required()
                    ->default('valid'),
                    
                Forms\Components\Textarea::make('scope')
                    ->label('Certification Scope')
                    ->rows(3),
                    
                Forms\Components\TagsInput::make('test_standards')
                    ->label('Test Standards')
                    ->placeholder('e.g., EN 55032, EN 55035')
                    ->helperText('Press Enter to add each standard'),
                    
                Forms\Components\FileUpload::make('certificate_file_path')
                    ->label('Certificate File')
                    ->disk('syncthing')
                    ->directory('certifications')
                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                    ->maxSize(10240) // 10MB
                    ->downloadable()
                    ->previewable(false),
                    
                Forms\Components\Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('component.name')
                    ->label('Component')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('component.manufacturer')
                    ->label('Manufacturer')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('certification_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'CE' => 'success',
                        'EMC' => 'info',
                        'LVD' => 'warning',
                        'RoHS' => 'purple',
                        'REACH' => 'orange',
                        default => 'gray',
                    }),
                    
                Tables\Columns\TextColumn::make('certificate_number')
                    ->label('Certificate #')
                    ->searchable()
                    ->copyable(),
                    
                Tables\Columns\TextColumn::make('issuing_authority')
                    ->label('Authority')
                    ->limit(30),
                    
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'valid' => 'success',
                        'pending' => 'warning',
                        'expired' => 'danger',
                        'revoked' => 'danger',
                        default => 'gray',
                    }),
                    
                Tables\Columns\TextColumn::make('issue_date')
                    ->label('Issued')
                    ->date()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Expires')
                    ->date()
                    ->sortable()
                    ->color(fn (ComponentCertification $record): string => 
                        !$record->expiry_date ? 'gray' : 
                        ($record->isExpiringSoon() ? 'danger' : 'success')),
                    
                Tables\Columns\TextColumn::make('days_until_expiry')
                    ->label('Days to Expiry')
                    ->getStateUsing(fn (ComponentCertification $record): ?string => 
                        $record->days_until_expiry !== null ? $record->days_until_expiry . ' days' : 'No expiry')
                    ->color(fn (ComponentCertification $record): string => 
                        $record->days_until_expiry === null ? 'gray' : 
                        ($record->days_until_expiry < 30 ? 'danger' : 
                        ($record->days_until_expiry < 90 ? 'warning' : 'success'))),
                    
                Tables\Columns\IconColumn::make('has_file')
                    ->label('File')
                    ->boolean()
                    ->getStateUsing(fn (ComponentCertification $record): bool => !empty($record->certificate_file_path)),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('certification_type')
                    ->options(ComponentCertification::getCeRelevantTypes()),
                    
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'valid' => 'Valid',
                        'pending' => 'Pending',
                        'expired' => 'Expired',
                        'revoked' => 'Revoked',
                    ]),
                    
                Tables\Filters\Filter::make('expiring_soon')
                    ->label('Expiring Soon (90 days)')
                    ->query(fn (Builder $query) => $query->expiringSoon()),
                    
                Tables\Filters\Filter::make('ce_relevant')
                    ->label('CE Relevant Only')
                    ->query(fn (Builder $query) => $query->ceRelevant()),
                    
                Tables\Filters\Filter::make('valid_only')
                    ->label('Valid Only')
                    ->query(fn (Builder $query) => $query->valid()),
                    
                Tables\Filters\Filter::make('no_file')
                    ->label('Missing Files')
                    ->query(fn (Builder $query) => $query->whereNull('certificate_file_path')),
            ])
            ->actions([
                Tables\Actions\Action::make('download_certificate')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (ComponentCertification $record): bool => !empty($record->certificate_file_path))
                    ->url(fn (ComponentCertification $record): string => 
                        Storage::disk('syncthing')->url($record->certificate_file_path))
                    ->openUrlInNewTab(),
                    
                Tables\Actions\Action::make('check_compliance')
                    ->label('Check Compliance')
                    ->icon('heroicon-o-shield-check')
                    ->action(function (ComponentCertification $record) {
                        $service = app(CertificationManagementService::class);
                        $analysis = $service->analyzeComponentCertifications($record->component);
                        
                        Notification::make()
                            ->title('Compliance Check')
                            ->body("Component compliance score: {$analysis['compliance_score']}% (Risk: {$analysis['risk_level']})")
                            ->success()
                            ->send();
                    }),
                    
                Tables\Actions\EditAction::make(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('check_expiring')
                    ->label('Check Expiring Certificates')
                    ->icon('heroicon-o-clock')
                    ->action(function () {
                        $service = app(CertificationManagementService::class);
                        $alerts = $service->checkExpiringCertifications();
                        
                        Notification::make()
                            ->title('Expiry Check Complete')
                            ->body(count($alerts) . ' certificates expiring within 90 days')
                            ->success()
                            ->send();
                    }),
                    
                Tables\Actions\Action::make('certification_statistics')
                    ->label('View Statistics')
                    ->icon('heroicon-o-chart-bar')
                    ->action(function () {
                        $service = app(CertificationManagementService::class);
                        $stats = $service->getCertificationStatistics();
                        
                        $message = "Total Components: {$stats['total_components']}\n";
                        foreach ($stats['certification_coverage'] as $type => $data) {
                            $message .= "{$data['name']}: {$data['coverage_percentage']}%\n";
                        }
                        
                        Notification::make()
                            ->title('Certification Statistics')
                            ->body($message)
                            ->info()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('mark_expired')
                    ->label('Mark as Expired')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        foreach ($records as $record) {
                            $record->update(['status' => 'expired']);
                        }
                        
                        Notification::make()
                            ->title('Bulk Update')
                            ->body('Selected certificates marked as expired')
                            ->success()
                            ->send();
                    }),
                    
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('expiry_date', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComponentCertifications::route('/'),
            'create' => Pages\CreateComponentCertification::route('/create'),
            'edit' => Pages\EditComponentCertification::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::expiringSoon()->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $count = static::getNavigationBadge();
        return $count > 0 ? 'danger' : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['component']);
    }
}