<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComponentImportResource\Pages;
use App\Models\ComponentImport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;

class ComponentImportResource extends Resource
{
    protected static ?string $model = ComponentImport::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationLabel = 'Storico Import';

    protected static ?string $modelLabel = 'Import';

    protected static ?string $pluralModelLabel = 'Import';

    protected static ?string $navigationGroup = 'Magazzino';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informazioni Import')
                    ->schema([
                        Forms\Components\TextInput::make('job_id')
                            ->label('Job ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('supplier')
                            ->label('Fornitore')
                            ->disabled(),
                        Forms\Components\TextInput::make('original_filename')
                            ->label('File Originale')
                            ->disabled()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('file_type')
                            ->label('Tipo File')
                            ->disabled(),
                        Forms\Components\Select::make('user_id')
                            ->label('Utente')
                            ->relationship('user', 'name')
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Statistiche')
                    ->schema([
                        Forms\Components\TextInput::make('components_imported')
                            ->label('Importati')
                            ->disabled()
                            ->suffix('componenti'),
                        Forms\Components\TextInput::make('components_updated')
                            ->label('Aggiornati')
                            ->disabled()
                            ->suffix('componenti'),
                        Forms\Components\TextInput::make('components_skipped')
                            ->label('Saltati')
                            ->disabled()
                            ->suffix('componenti'),
                        Forms\Components\TextInput::make('components_failed')
                            ->label('Falliti')
                            ->disabled()
                            ->suffix('componenti'),
                        Forms\Components\TextInput::make('movements_created')
                            ->label('Movimenti Creati')
                            ->disabled()
                            ->suffix('movimenti'),
                        Forms\Components\Placeholder::make('success_rate')
                            ->label('Tasso di Successo')
                            ->content(fn ($record) => $record ? $record->success_rate . '%' : 'N/A'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Fattura')
                    ->schema([
                        Forms\Components\TextInput::make('invoice_number')
                            ->label('Numero Fattura')
                            ->disabled(),
                        Forms\Components\DatePicker::make('invoice_date')
                            ->label('Data Fattura')
                            ->disabled(),
                        Forms\Components\TextInput::make('invoice_total')
                            ->label('Totale Fattura')
                            ->disabled()
                            ->prefix('€'),
                        Forms\Components\Select::make('destination_project_id')
                            ->label('Progetto Destinazione')
                            ->relationship('destinationProject', 'name')
                            ->disabled(),
                    ])
                    ->columns(2)
                    ->collapsed(),

                Forms\Components\Section::make('Stato e Timing')
                    ->schema([
                        Forms\Components\TextInput::make('status')
                            ->label('Stato')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('started_at')
                            ->label('Inizio')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('completed_at')
                            ->label('Completamento')
                            ->disabled(),
                        Forms\Components\Placeholder::make('duration')
                            ->label('Durata')
                            ->content(fn ($record) => $record ? ($record->human_duration ?? 'N/A') : 'N/A'),
                        Forms\Components\Textarea::make('error_message')
                            ->label('Messaggio di Errore')
                            ->disabled()
                            ->columnSpanFull()
                            ->visible(fn ($record) => $record && $record->error_message),
                    ])
                    ->columns(2)
                    ->collapsed(),

                Forms\Components\Section::make('Dettagli Import')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Note')
                            ->disabled()
                            ->columnSpanFull(),
                        Forms\Components\KeyValue::make('field_mapping')
                            ->label('Mappatura Campi')
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('original_filename')
                    ->label('File')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->original_filename),
                Tables\Columns\TextColumn::make('supplier')
                    ->label('Fornitore')
                    ->searchable()
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower($state)) {
                        'mouser' => 'info',
                        'digikey' => 'warning',
                        'farnell' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Utente')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('components_imported')
                    ->label('Importati')
                    ->sortable()
                    ->alignCenter()
                    ->color('success'),
                Tables\Columns\TextColumn::make('components_updated')
                    ->label('Aggiornati')
                    ->sortable()
                    ->alignCenter()
                    ->color('info'),
                Tables\Columns\TextColumn::make('components_failed')
                    ->label('Falliti')
                    ->sortable()
                    ->alignCenter()
                    ->color('danger')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('movements_created')
                    ->label('Movimenti')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('success_rate')
                    ->label('Successo')
                    ->suffix('%')
                    ->sortable()
                    ->alignCenter()
                    ->color(fn ($state) => $state >= 90 ? 'success' : ($state >= 70 ? 'warning' : 'danger'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Fattura')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'processing' => 'warning',
                        'failed' => 'danger',
                        'pending' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'completed' => 'Completato',
                        'processing' => 'In Elaborazione',
                        'failed' => 'Fallito',
                        'pending' => 'In Attesa',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('started_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->default(now()),
                Tables\Columns\TextColumn::make('human_duration')
                    ->label('Durata')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creato')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('supplier')
                    ->label('Fornitore')
                    ->options([
                        'mouser' => 'Mouser',
                        'digikey' => 'DigiKey',
                        'farnell' => 'Farnell',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Stato')
                    ->options([
                        'completed' => 'Completato',
                        'processing' => 'In Elaborazione',
                        'failed' => 'Fallito',
                        'pending' => 'In Attesa',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Dal'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Al'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
                Tables\Filters\TrashedFilter::make()
                    ->label('Eliminati'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Dettagli'),
                Tables\Actions\Action::make('delete_with_data')
                    ->label('Elimina con Dati')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Elimina Import e Dati Correlati')
                    ->modalDescription(fn ($record) =>
                        "Sei sicuro di voler eliminare questo import?\n\n" .
                        "Questa azione eliminerà:\n" .
                        "• {$record->movements_created} movimenti di inventario\n" .
                        "• Le quantità verranno sottratte dal magazzino\n" .
                        "• I componenti rimarranno nel sistema\n\n" .
                        "L'azione è irreversibile!"
                    )
                    ->modalSubmitActionLabel('Elimina Tutto')
                    ->action(function (ComponentImport $record) {
                        try {
                            $record->deleteWithRelatedData();

                            Notification::make()
                                ->title('Import Eliminato')
                                ->body("Import #{$record->id} e tutti i dati correlati sono stati eliminati con successo.")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Log::error('Failed to delete import', [
                                'import_id' => $record->id,
                                'error' => $e->getMessage()
                            ]);

                            Notification::make()
                                ->title('Errore')
                                ->body('Impossibile eliminare l\'import: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Elimina Selezionati'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListComponentImports::route('/'),
            'view' => Pages\ViewComponentImport::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function canCreate(): bool
    {
        return false; // Disable manual creation - imports are created automatically
    }
}
