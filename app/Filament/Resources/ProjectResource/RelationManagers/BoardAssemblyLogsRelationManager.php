<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\BoardAssemblyLog;
use App\Services\NextcloudService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BoardAssemblyLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'boardAssemblyLogs';

    protected static ?string $title = 'Storico Saldo/Assemblaggio Schede';

    protected static ?string $modelLabel = 'assemblaggio';

    protected static ?string $pluralModelLabel = 'assemblaggi';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Tabs')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Assemblaggio')
                            ->schema([
                                Forms\Components\Section::make('Dettagli Assemblaggio')
                                    ->schema([
                                        Forms\Components\DatePicker::make('assembly_date')
                                            ->label('Data Assemblaggio')
                                            ->required()
                                            ->default(now())
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->maxDate(now()),

                                        Forms\Components\TextInput::make('boards_count')
                                            ->label('Numero Schede Assemblate')
                                            ->required()
                                            ->numeric()
                                            ->minValue(1)
                                            ->suffix('schede')
                                            ->helperText(function () {
                                                $project = $this->getOwnerRecord();
                                                $totalOrdered = $project->total_boards_ordered;
                                                $alreadyAssembled = $project->boards_assembled;
                                                $remaining = $totalOrdered - $alreadyAssembled;

                                                return "Ordinate: {$totalOrdered} | Già assemblate: {$alreadyAssembled} | Rimanenti: {$remaining}";
                                            })
                                            ->live()
                                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                $project = $this->getOwnerRecord();
                                                $totalOrdered = $project->total_boards_ordered;
                                                $alreadyAssembled = $project->boards_assembled;
                                                $remaining = $totalOrdered - $alreadyAssembled;

                                                if ($state > $remaining) {
                                                    \Filament\Notifications\Notification::make()
                                                        ->warning()
                                                        ->title('Attenzione')
                                                        ->body("Stai assemblando più schede ({$state}) di quelle rimanenti ({$remaining})")
                                                        ->send();
                                                }
                                            }),

                                        Forms\Components\Toggle::make('is_prototype')
                                            ->label('Test/Prototipo')
                                            ->helperText('Attiva se assemblaggio di test/prototipo (non conta nella produzione)')
                                            ->default(false)
                                            ->live(),

                                        Forms\Components\Placeholder::make('batch_preview')
                                            ->label('Numero Batch (generato automaticamente)')
                                            ->content(function (Forms\Get $get) {
                                                $project = $this->getOwnerRecord();
                                                $isPrototype = $get('is_prototype') ?? false;
                                                return \App\Models\BoardAssemblyLog::generateBatchNumber($project, $isPrototype);
                                            }),

                                        Forms\Components\Select::make('status')
                                            ->label('Stato')
                                            ->required()
                                            ->options([
                                                'assembled' => 'Assemblato',
                                                'tested' => 'Testato (OK)',
                                                'failed' => 'Test Fallito',
                                                'rework' => 'Rework Necessario',
                                            ])
                                            ->default('assembled')
                                            ->native(false),

                                        Forms\Components\Textarea::make('notes')
                                            ->label('Note e Osservazioni')
                                            ->rows(3)
                                            ->placeholder('Eventuali problemi riscontrati, componenti mancanti, annotazioni...'),
                                    ]),

                                Forms\Components\Section::make('Documenti QC')
                                    ->schema([
                                        Forms\Components\FileUpload::make('qc_documents')
                                            ->label('Carica Documenti QC')
                                            ->multiple()
                                            ->maxFiles(10)
                                            ->maxSize(10240) // 10MB
                                            ->acceptedFileTypes(['image/*', 'application/pdf'])
                                            ->directory('temp/qc-uploads')
                                            ->helperText('Foto delle schede, test reports, certificati di qualità (max 10 file, 10MB ciascuno)')
                                            ->preserveFilenames()
                                            ->dehydrated(false), // Non salvarlo nel database
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('DDT')
                            ->schema([
                                Forms\Components\Section::make('Informazioni DDT')
                                    ->description('Genera il Documento Di Trasporto per questa consegna')
                                    ->schema([
                                        Forms\Components\TextInput::make('ddt_number')
                                            ->label('Numero DDT')
                                            ->helperText('Lascia vuoto per generazione automatica')
                                            ->maxLength(255),

                                        Forms\Components\DatePicker::make('ddt_date')
                                            ->label('Data DDT')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->default(now()),

                                        Forms\Components\Select::make('ddt_transport_type')
                                            ->label('Trasporto a cura di')
                                            ->options([
                                                'cedente' => 'Cedente (Mittente)',
                                                'cessionario' => 'Cessionario (Destinatario)',
                                            ])
                                            ->default('cedente')
                                            ->required()
                                            ->native(false),

                                        Forms\Components\Select::make('ddt_payment_condition')
                                            ->label('Condizione di Pagamento')
                                            ->options([
                                                'in_conto' => 'In Conto (Acconto)',
                                                'in_saldo' => 'In Saldo (Finale)',
                                            ])
                                            ->helperText('Viene suggerita automaticamente in base al tipo di assemblaggio')
                                            ->native(false),

                                        Forms\Components\Textarea::make('ddt_reason')
                                            ->label('Causale del Trasporto')
                                            ->helperText('Viene generata automaticamente in base al contesto')
                                            ->rows(2)
                                            ->maxLength(500),

                                        Forms\Components\Textarea::make('ddt_goods_description')
                                            ->label('Descrizione Merce')
                                            ->helperText('Verrà generata automaticamente con AI se disponibile')
                                            ->rows(2)
                                            ->maxLength(500),
                                    ]),

                                Forms\Components\Section::make('Dettagli Spedizione')
                                    ->schema([
                                        Forms\Components\TextInput::make('ddt_packages_count')
                                            ->label('Numero Colli')
                                            ->numeric()
                                            ->default(1)
                                            ->minValue(1)
                                            ->required(),

                                        Forms\Components\TextInput::make('ddt_weight_kg')
                                            ->label('Peso (kg)')
                                            ->numeric()
                                            ->step(0.01)
                                            ->suffix('kg'),

                                        Forms\Components\TextInput::make('ddt_appearance')
                                            ->label('Aspetto Esteriore')
                                            ->default('scatola')
                                            ->maxLength(255),

                                        Forms\Components\KeyValue::make('ddt_delivery_address')
                                            ->label('Indirizzo di Consegna (se diverso dal cliente)')
                                            ->helperText('Lascia vuoto per usare l\'indirizzo del cliente')
                                            ->keyLabel('Campo')
                                            ->valueLabel('Valore')
                                            ->addButtonLabel('Aggiungi campo'),
                                    ]),
                            ])
                            ->visibleOn(['edit', 'view']),
                    ]),

                Forms\Components\Hidden::make('user_id')
                    ->default(Auth::id()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('assembly_date')
            ->defaultSort('assembly_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('assembly_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('boards_count')
                    ->label('N. Schede')
                    ->suffix(' schede')
                    ->alignCenter()
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('Totale'),
                    ]),

                Tables\Columns\IconColumn::make('is_prototype')
                    ->label('Tipo')
                    ->boolean()
                    ->trueIcon('heroicon-o-beaker')
                    ->falseIcon('heroicon-o-cog')
                    ->trueColor('warning')
                    ->falseColor('success')
                    ->tooltip(fn ($record) => $record->is_prototype ? 'Test/Prototipo' : 'Produzione')
                    ->sortable(),

                Tables\Columns\TextColumn::make('batch_number')
                    ->label('Lotto')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->placeholder('N/A')
                    ->copyable()
                    ->copyMessage('Batch copiato!'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Stato')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'assembled' => 'Assemblato',
                        'tested' => 'Testato',
                        'failed' => 'Fallito',
                        'rework' => 'Rework',
                        default => $state,
                    })
                    ->colors([
                        'info' => 'assembled',
                        'success' => 'tested',
                        'danger' => 'failed',
                        'warning' => 'rework',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('qcDocuments_count')
                    ->label('Doc. QC')
                    ->counts('qcDocuments')
                    ->alignCenter()
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn ($state) => $state > 0 ? $state : '-'),

                Tables\Columns\TextColumn::make('qrCodes_count')
                    ->label('QR Codes')
                    ->counts('qrCodes')
                    ->alignCenter()
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => $state > 0 ? $state : '-')
                    ->url(fn ($record) => $record->qrCodes_count > 0 ? route('filament.admin.resources.projects.board-assembly-logs.download-qr', [
                        'project' => $record->project_id,
                        'assemblyLog' => $record->id
                    ]) : null)
                    ->openUrlInNewTab()
                    ->tooltip(fn ($record) => $record->qrCodes_count > 0 ? "Clicca per scaricare tutti i QR codes" : 'Nessun QR code generato'),

                Tables\Columns\TextColumn::make('assemblyChecklist_count')
                    ->label('Checklist')
                    ->counts('assemblyChecklist')
                    ->alignCenter()
                    ->badge()
                    ->color(function ($state) {
                        return $state > 0 ? 'success' : 'gray';
                    })
                    ->formatStateUsing(function ($state, $record) {
                        if ($state > 0 && $record->assemblyChecklist->first()) {
                            return number_format($record->assemblyChecklist->first()->completion_percentage, 0) . '%';
                        }
                        return 'N/A';
                    })
                    ->tooltip(function ($record) {
                        if ($record->assemblyChecklist_count > 0 && $record->assemblyChecklist->first()) {
                            $checklist = $record->assemblyChecklist->first();
                            return "Status: {$checklist->status} | Items: {$checklist->completed_items}/{$checklist->total_items}";
                        }
                        return 'Nessuna checklist generata';
                    }),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Operatore')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('ddt_status_label')
                    ->label('DDT')
                    ->colors([
                        'gray' => 'Non Generato',
                        'warning' => 'Generato',
                        'success' => 'Firmato',
                    ])
                    ->icons([
                        'heroicon-o-x-circle' => 'Non Generato',
                        'heroicon-o-document' => 'Generato',
                        'heroicon-o-check-circle' => 'Firmato',
                    ])
                    ->sortable(query: function ($query, $direction) {
                        return $query->orderBy('ddt_number', $direction);
                    })
                    ->tooltip(fn ($record) => $record->ddt_number ?? 'Nessun DDT generato'),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Note')
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    })
                    ->toggleable()
                    ->placeholder('Nessuna nota'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registrato il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Stato')
                    ->options([
                        'assembled' => 'Assemblato',
                        'tested' => 'Testato',
                        'failed' => 'Fallito',
                        'rework' => 'Rework',
                    ]),

                Tables\Filters\Filter::make('assembly_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Da')
                            ->native(false),
                        Forms\Components\DatePicker::make('until')
                            ->label('A')
                            ->native(false),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('assembly_date', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('assembly_date', '<=', $date));
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nuova Registrazione Assemblaggio')
                    ->icon('heroicon-o-plus-circle')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = Auth::id();

                        // Generate automatic batch number
                        $project = $this->getOwnerRecord();
                        $isPrototype = $data['is_prototype'] ?? false;
                        $data['batch_number'] = \App\Models\BoardAssemblyLog::generateBatchNumber($project, $isPrototype);

                        return $data;
                    })
                    ->after(function (BoardAssemblyLog $record, array $data) {
                        // Upload documenti QC su Nextcloud
                        if (!empty($data['qc_documents'])) {
                            $this->uploadQcDocumentsToNextcloud($record, $data['qc_documents']);
                        }

                        // Ricalcola boards_assembled del progetto
                        $this->recalculateBoardsAssembled($record->project);

                        // Refresh per ottenere i dati aggiornati
                        $record->refresh();
                        $record->project->refresh();

                        // Invia email di notifica
                        $this->sendAssemblyNotificationEmail($record);
                    })
                    ->successNotificationTitle('Assemblaggio registrato con successo'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading(fn (BoardAssemblyLog $record) => 'Dettagli Assemblaggio del ' . $record->assembly_date->format('d/m/Y'))
                    ->modalWidth('7xl'),

                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Rimuovi qc_documents dall'update (gestito separatamente)
                        unset($data['qc_documents']);
                        return $data;
                    })
                    ->after(function (BoardAssemblyLog $record) {
                        // Ricalcola boards_assembled del progetto
                        $this->recalculateBoardsAssembled($record->project);
                    }),

                Tables\Actions\Action::make('viewQcDocuments')
                    ->label('Visualizza QC')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->visible(fn (BoardAssemblyLog $record) => $record->qcDocuments->isNotEmpty())
                    ->modalHeading('Documenti QC')
                    ->modalContent(fn (BoardAssemblyLog $record) => view('filament.modals.qc-documents', [
                        'documents' => $record->qcDocuments,
                        'assemblyLog' => $record,
                    ]))
                    ->modalWidth('3xl'),

                Tables\Actions\Action::make('viewQrCodes')
                    ->label('Visualizza QR')
                    ->icon('heroicon-o-qr-code')
                    ->color('info')
                    ->visible(fn (BoardAssemblyLog $record) => $record->qrCodes->isNotEmpty())
                    ->modalHeading(fn (BoardAssemblyLog $record) => 'QR Codes - ' . $record->batch_number)
                    ->modalContent(fn (BoardAssemblyLog $record) => view('filament.modals.board-qr-codes', [
                        'qrCodes' => $record->qrCodes,
                        'assemblyLog' => $record,
                    ]))
                    ->modalWidth('5xl'),

                Tables\Actions\Action::make('viewChecklist')
                    ->label('Checklist')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('success')
                    ->visible(fn (BoardAssemblyLog $record) => $record->assemblyChecklist->isNotEmpty())
                    ->modalHeading(fn (BoardAssemblyLog $record) => 'Assembly Checklist - ' . $record->batch_number)
                    ->modalContent(fn (BoardAssemblyLog $record) => view('filament.modals.assembly-checklist', [
                        'checklist' => $record->assemblyChecklist->first(),
                        'assemblyLog' => $record,
                    ]))
                    ->modalWidth('7xl')
                    ->slideOver(),

                Tables\Actions\Action::make('generateChecklist')
                    ->label('Genera Checklist')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->visible(fn (BoardAssemblyLog $record) => $record->assemblyChecklist->isEmpty())
                    ->requiresConfirmation()
                    ->modalHeading('Genera Assembly Checklist')
                    ->modalDescription('Vuoi generare una checklist di assemblaggio per questo log? La checklist sarà generata automaticamente con AI se disponibile.')
                    ->action(function (BoardAssemblyLog $record) {
                        $service = new \App\Services\AssemblyChecklistService();
                        $checklist = $service->generateChecklistForAssembly($record);

                        if ($checklist) {
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Checklist Generata')
                                ->body("Checklist con {$checklist->total_items} items creata con successo")
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Errore')
                                ->body('Impossibile generare la checklist')
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('regenerateChecklist')
                    ->label('Rigenera')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (BoardAssemblyLog $record) => $record->assemblyChecklist->isNotEmpty())
                    ->requiresConfirmation()
                    ->modalHeading('Rigenera Assembly Checklist')
                    ->modalDescription('Vuoi rigenerare la checklist? Quella esistente sarà eliminata e ne sarà creata una nuova.')
                    ->action(function (BoardAssemblyLog $record) {
                        $service = new \App\Services\AssemblyChecklistService();
                        $checklist = $record->assemblyChecklist->first();

                        if ($checklist) {
                            $newChecklist = $service->regenerateChecklist($checklist);

                            if ($newChecklist) {
                                \Filament\Notifications\Notification::make()
                                    ->success()
                                    ->title('Checklist Rigenerata')
                                    ->body("Nuova checklist con {$newChecklist->total_items} items creata")
                                    ->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->danger()
                                    ->title('Errore')
                                    ->body('Impossibile rigenerare la checklist')
                                    ->send();
                            }
                        }
                    }),

                Tables\Actions\Action::make('generateDdt')
                    ->label('Genera DDT')
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->visible(fn (BoardAssemblyLog $record) => !$record->hasDdt())
                    ->requiresConfirmation()
                    ->modalHeading('Genera Documento Di Trasporto')
                    ->modalDescription('Vuoi generare il DDT per questo assemblaggio? Verrà caricato automaticamente su Nextcloud.')
                    ->action(function (BoardAssemblyLog $record) {
                        $service = new \App\Services\DdtService();
                        $success = $service->generateAndUploadDdt($record);

                        if ($success) {
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('DDT Generato')
                                ->body("DDT {$record->ddt_number} generato e caricato su Nextcloud")
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Errore')
                                ->body('Impossibile generare il DDT')
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('viewDdt')
                    ->label('Visualizza DDT')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->visible(fn (BoardAssemblyLog $record) => $record->hasDdt())
                    ->url(fn (BoardAssemblyLog $record) => route('filament.admin.resources.projects.board-assembly-logs.view-ddt', [
                        'project' => $record->project_id,
                        'assemblyLog' => $record->id
                    ]))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('editDdt')
                    ->label('Modifica DDT')
                    ->icon('heroicon-o-pencil')
                    ->color('warning')
                    ->visible(fn (BoardAssemblyLog $record) => $record->hasDdt())
                    ->form(function (BoardAssemblyLog $record) {
                        return [
                            Forms\Components\Section::make('Informazioni DDT')
                                ->schema([
                                    Forms\Components\TextInput::make('ddt_number')
                                        ->label('Numero DDT')
                                        ->default($record->ddt_number)
                                        ->required(),
                                    Forms\Components\DatePicker::make('ddt_date')
                                        ->label('Data Consegna')
                                        ->default($record->ddt_date)
                                        ->required(),
                                    Forms\Components\Select::make('ddt_transport_type')
                                        ->label('Tipo Trasporto')
                                        ->options([
                                            'cedente' => 'Cedente',
                                            'cessionario' => 'Cessionario',
                                        ])
                                        ->default($record->ddt_transport_type)
                                        ->required(),
                                ]),
                            Forms\Components\Section::make('Destinazione')
                                ->schema([
                                    Forms\Components\Textarea::make('ddt_delivery_address')
                                        ->label('Indirizzo Destinazione (JSON)')
                                        ->default(json_encode($record->ddt_delivery_address, JSON_PRETTY_PRINT))
                                        ->helperText('Lascia vuoto per usare indirizzo cliente'),
                                ]),
                            Forms\Components\Section::make('Dettagli Spedizione')
                                ->schema([
                                    Forms\Components\Textarea::make('ddt_reason')
                                        ->label('Causale Trasporto')
                                        ->default($record->ddt_reason)
                                        ->rows(2),
                                    Forms\Components\Select::make('ddt_payment_condition')
                                        ->label('Condizione Pagamento')
                                        ->options([
                                            'in_conto' => 'In Conto',
                                            'in_saldo' => 'In Saldo',
                                        ])
                                        ->default($record->ddt_payment_condition),
                                    Forms\Components\TextInput::make('ddt_packages_count')
                                        ->label('N. Colli')
                                        ->numeric()
                                        ->default($record->ddt_packages_count),
                                    Forms\Components\TextInput::make('ddt_weight_kg')
                                        ->label('Peso (kg)')
                                        ->numeric()
                                        ->step(0.1)
                                        ->default($record->ddt_weight_kg),
                                    Forms\Components\TextInput::make('ddt_appearance')
                                        ->label('Aspetto Esteriore')
                                        ->default($record->ddt_appearance),
                                    Forms\Components\Textarea::make('ddt_goods_description')
                                        ->label('Descrizione Beni')
                                        ->default($record->ddt_goods_description)
                                        ->rows(3),
                                ]),
                        ];
                    })
                    ->action(function (BoardAssemblyLog $record, array $data) {
                        // Update DDT fields
                        $updateData = [
                            'ddt_number' => $data['ddt_number'],
                            'ddt_date' => $data['ddt_date'],
                            'ddt_transport_type' => $data['ddt_transport_type'],
                            'ddt_reason' => $data['ddt_reason'] ?? null,
                            'ddt_payment_condition' => $data['ddt_payment_condition'] ?? null,
                            'ddt_packages_count' => $data['ddt_packages_count'] ?? 1,
                            'ddt_weight_kg' => $data['ddt_weight_kg'] ?? null,
                            'ddt_appearance' => $data['ddt_appearance'] ?? 'scatola',
                            'ddt_goods_description' => $data['ddt_goods_description'] ?? null,
                        ];

                        // Handle delivery address JSON
                        if (!empty($data['ddt_delivery_address'])) {
                            $updateData['ddt_delivery_address'] = json_decode($data['ddt_delivery_address'], true);
                        }

                        $record->update($updateData);

                        // Regenerate DDT PDF
                        $service = new \App\Services\DdtService();
                        $success = $service->generateAndUploadDdt($record);

                        if ($success) {
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('DDT Aggiornato')
                                ->body("DDT {$record->ddt_number} modificato e rigenerato")
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->warning()
                                ->title('DDT Salvato')
                                ->body('Modifiche salvate ma impossibile rigenerare il PDF')
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('downloadDdt')
                    ->label('Scarica DDT')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (BoardAssemblyLog $record) => $record->hasDdt())
                    ->action(function (BoardAssemblyLog $record) {
                        $service = new \App\Services\DdtService();
                        $signed = $record->isDdtSigned();
                        $pdfPath = $service->downloadDdtPdf($record, $signed);

                        if ($pdfPath && file_exists($pdfPath)) {
                            return response()->download($pdfPath)->deleteFileAfterSend(true);
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Errore')
                                ->body('Impossibile scaricare il DDT')
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('signDdt')
                    ->label('Firma DDT')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn (BoardAssemblyLog $record) => $record->hasDdt() && !$record->isDdtSigned())
                    ->form([
                        Forms\Components\Placeholder::make('info')
                            ->content('Le firme digitali verranno aggiunte al DDT. Per ora compila manualmente il PDF scaricato e ricaricalo come firmato.'),
                        Forms\Components\Textarea::make('conductor_signature')
                            ->label('Firma Conducente')
                            ->helperText('Nome e cognome del conducente'),
                        Forms\Components\Textarea::make('recipient_signature')
                            ->label('Firma Destinatario')
                            ->helperText('Nome e cognome del destinatario'),
                    ])
                    ->action(function (BoardAssemblyLog $record, array $data) {
                        $service = new \App\Services\DdtService();
                        $success = $service->updateSignatures(
                            $record,
                            $data['conductor_signature'] ?? null,
                            $data['recipient_signature'] ?? null
                        );

                        if ($success) {
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('DDT Firmato')
                                ->body('Le firme sono state aggiunte al DDT')
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Errore')
                                ->body('Impossibile aggiungere le firme')
                                ->send();
                        }
                    }),

                Tables\Actions\DeleteAction::make()
                    ->after(function (BoardAssemblyLog $record) {
                        // Ricalcola boards_assembled del progetto
                        $this->recalculateBoardsAssembled($record->project);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(function () {
                            // Ricalcola boards_assembled del progetto
                            $this->recalculateBoardsAssembled($this->getOwnerRecord());
                        }),
                ]),
            ])
            ->emptyStateHeading('Nessun assemblaggio registrato')
            ->emptyStateDescription('Inizia a tracciare l\'assemblaggio delle schede PCB')
            ->emptyStateIcon('heroicon-o-wrench-screwdriver');
    }

    /**
     * Upload QC documents to Nextcloud.
     */
    protected function uploadQcDocumentsToNextcloud(BoardAssemblyLog $assemblyLog, array $filePaths): void
    {
        try {
            $nextcloudService = new NextcloudService();
            $project = $assemblyLog->project;

            // Verifica che il progetto abbia la cartella Nextcloud
            if (!$project->nextcloud_folder_created) {
                Log::warning('Project Nextcloud folder not created', ['project_id' => $project->id]);
                return;
            }

            $projectBasePath = $nextcloudService->getProjectBasePath($project);
            $qcBasePath = "{$projectBasePath}/03_Produzione/Test_Reports";

            // Organizza per data e batch
            $dateFolder = $assemblyLog->assembly_date->format('Y-m-d');
            $batchFolder = $assemblyLog->batch_number ? "_{$assemblyLog->batch_number}" : '';
            $uploadPath = "{$qcBasePath}/{$dateFolder}{$batchFolder}";

            // Assicura che la cartella esista
            $nextcloudService->ensureFolderExists($uploadPath);

            // Upload ogni file
            foreach ($filePaths as $filePath) {
                $fullPath = Storage::disk('public')->path($filePath);
                if (file_exists($fullPath)) {
                    $filename = basename($filePath);
                    $remotePath = "{$uploadPath}/{$filename}";

                    // Upload su Nextcloud
                    $uploaded = $nextcloudService->helper->uploadFile($fullPath, $remotePath);

                    if ($uploaded) {
                        // Crea record Document per tracking
                        $assemblyLog->qcDocuments()->create([
                            'title' => $filename,
                            'file_path' => $remotePath,
                            'file_size' => filesize($fullPath),
                            'mime_type' => mime_content_type($fullPath),
                            'type' => 'qc_document',
                            'uploaded_by' => Auth::id(),
                        ]);

                        Log::info('QC document uploaded', [
                            'assembly_log_id' => $assemblyLog->id,
                            'filename' => $filename,
                            'path' => $remotePath,
                        ]);
                    }

                    // Rimuovi file temporaneo
                    Storage::disk('public')->delete($filePath);
                }
            }

            \Filament\Notifications\Notification::make()
                ->success()
                ->title('Documenti QC caricati')
                ->body(count($filePaths) . ' documenti caricati su Nextcloud')
                ->send();

        } catch (\Exception $e) {
            Log::error('Error uploading QC documents to Nextcloud', [
                'error' => $e->getMessage(),
                'assembly_log_id' => $assemblyLog->id,
            ]);

            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('Errore upload documenti')
                ->body('Errore durante il caricamento dei documenti QC su Nextcloud')
                ->send();
        }
    }

    /**
     * Recalculate total boards assembled for the project.
     * Only counts production boards (is_prototype = false).
     */
    protected function recalculateBoardsAssembled(Model $project): void
    {
        try {
            $totalAssembled = $project->boardAssemblyLogs()
                ->whereIn('status', ['assembled', 'tested'])
                ->where('is_prototype', false) // Only production boards
                ->sum('boards_count');

            $project->update(['boards_assembled' => $totalAssembled]);

            Log::info('Project boards_assembled recalculated', [
                'project_id' => $project->id,
                'boards_assembled' => $totalAssembled,
            ]);

        } catch (\Exception $e) {
            Log::error('Error recalculating boards_assembled', [
                'error' => $e->getMessage(),
                'project_id' => $project->id,
            ]);
        }
    }

    /**
     * Send email notification when boards are assembled.
     */
    protected function sendAssemblyNotificationEmail(BoardAssemblyLog $assemblyLog): void
    {
        try {
            $project = $assemblyLog->project;
            $adminEmail = 'alessandro.cursoli@supernovaindustries.it';

            // Get client email from project or customer
            $clientEmail = $project->client_email ?: $project->customer->email;

            // Always send to admin
            $mail = \Illuminate\Support\Facades\Mail::to($adminEmail);

            // CC to client if email is available
            if ($clientEmail) {
                $mail->cc($clientEmail);
            }

            $mail->send(new \App\Mail\BoardsAssembledMail($project, $assemblyLog));

            Log::info('Boards assembled email sent', [
                'project_id' => $project->id,
                'project_code' => $project->code,
                'assembly_log_id' => $assemblyLog->id,
                'boards_count' => $assemblyLog->boards_count,
                'admin_email' => $adminEmail,
                'client_email' => $clientEmail ?? 'none',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send boards assembled email', [
                'assembly_log_id' => $assemblyLog->id,
                'project_id' => $assemblyLog->project_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
