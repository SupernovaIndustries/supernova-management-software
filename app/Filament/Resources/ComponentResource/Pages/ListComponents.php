<?php

namespace App\Filament\Resources\ComponentResource\Pages;

use App\Filament\Resources\ComponentResource;
use App\Services\ComponentImportService;
use App\Services\NextcloudService;
use App\Services\Suppliers\MouserApiService;
use App\Models\Category;
use App\Jobs\ImportComponentsJob;
use App\Jobs\EnrichComponentsWithDatasheetJob;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListComponents extends ListRecords
{
    protected static string $resource = ComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('monitor_import')
                ->label('Monitor Import')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->url(route('import-monitor'))
                ->openUrlInNewTab(),

            Actions\Action::make('enrich_components')
                ->label('Arricchisci Componenti')
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Arricchisci Componenti con Datasheet')
                ->modalDescription('Estrai automaticamente le specifiche tecniche dai datasheet per i componenti esistenti.')
                ->modalSubmitActionLabel('Avvia Arricchimento')
                ->form([
                    Forms\Components\Toggle::make('missing_specs_only')
                        ->label('Solo componenti con specifiche mancanti')
                        ->default(true)
                        ->helperText('Arricchisce solo componenti con package_type, value, voltage_rating o mounting_type vuoti'),
                    Forms\Components\Select::make('category_id')
                        ->label('Categoria (opzionale)')
                        ->options(fn () => Category::pluck('name', 'id'))
                        ->searchable()
                        ->placeholder('Tutte le categorie')
                        ->helperText('Limita arricchimento a una categoria specifica'),
                ])
                ->action(function (array $data) {
                    // Generate unique Job ID
                    $jobId = uniqid('enrich_', true);

                    // Dispatch Job for background processing
                    EnrichComponentsWithDatasheetJob::dispatch(
                        $jobId,
                        auth()->id(),
                        $data
                    );

                    // Notification
                    Notification::make()
                        ->title('✅ Arricchimento avviato')
                        ->body("Reindirizzamento alla pagina di monitoraggio...")
                        ->success()
                        ->send();

                    // Redirect to progress page
                    return redirect("/admin/enrich-progress/{$jobId}");
                }),

            Actions\CreateAction::make(),

            Actions\Action::make('import_components')
                ->label('Import Componenti')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    Forms\Components\Tabs::make('Import Components')
                        ->tabs([
                            Forms\Components\Tabs\Tab::make('File Componenti')
                                ->schema([
                                    Forms\Components\FileUpload::make('file')
                                        ->acceptedFileTypes([
                                            'text/csv', 
                                            'application/csv',
                                            'application/vnd.ms-excel',
                                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                            'application/excel'
                                        ])
                                        ->required()
                                        ->helperText('Supporta file CSV ed Excel (.xlsx, .xls). Excel è consigliato per evitare problemi di encoding.'),
                                    Forms\Components\Select::make('supplier')
                                        ->options([
                                            'mouser' => 'Mouser Electronics',
                                            'digikey' => 'DigiKey Electronics',
                                            'farnell' => 'Farnell Element14',
                                        ])
                                        ->required()
                                        ->helperText('Seleziona il fornitore per applicare il mapping delle colonne corretto.'),
                                ]),
                            Forms\Components\Tabs\Tab::make('Fattura d\'Acquisto')
                                ->schema([
                                    Forms\Components\FileUpload::make('invoice_file')
                                        ->label('Fattura PDF')
                                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                        ->required()
                                        ->disk('public')
                                        ->directory('temp_invoices')
                                        ->helperText('Carica la fattura d\'acquisto. Verrà salvata in Nextcloud: Documenti SRL/Fatture Fornitori/[Anno]/[Fornitore]'),
                                    Forms\Components\TextInput::make('invoice_number')
                                        ->label('Numero Fattura')
                                        ->required()
                                        ->maxLength(50)
                                        ->helperText('Es: INV-2024-001234'),
                                    Forms\Components\DatePicker::make('invoice_date')
                                        ->label('Data Fattura')
                                        ->required()
                                        ->default(now()),
                                    Forms\Components\TextInput::make('invoice_total')
                                        ->label('Totale Fattura (€)')
                                        ->numeric()
                                        ->prefix('€')
                                        ->helperText('Importo totale della fattura IVA inclusa'),
                                    Forms\Components\Select::make('project_id')
                                        ->label('Progetto Destinazione (opzionale)')
                                        ->options(\App\Models\Project::pluck('name', 'id'))
                                        ->searchable()
                                        ->placeholder('Nessun progetto specifico')
                                        ->helperText('Se i componenti sono destinati a un progetto specifico, selezionalo qui. Apparirà collegato nei movimenti di inventario.'),
                                    Forms\Components\Textarea::make('notes')
                                        ->label('Note')
                                        ->placeholder('Note aggiuntive sull\'acquisto...')
                                        ->rows(3),
                                ]),
                        ])
                        ->columnSpanFull(),
                ])
                ->action(function (array $data) {
                    // Reduce timeout - Job handles the long-running task
                    set_time_limit(60);

                    try {
                        // Upload invoice to Nextcloud (Magazzino folder - convention)
                        $nextcloudPath = null;
                        if (!empty($data['invoice_file'])) {
                            $supplierName = match($data['supplier']) {
                                'mouser' => 'Mouser',
                                'digikey' => 'DigiKey',
                                'farnell' => 'Farnell',
                                default => 'Altri'
                            };

                            $year = date('Y');
                            $invoiceNumber = $data['invoice_number'];
                            $invoiceDate = date('Ymd', strtotime($data['invoice_date']));

                            // Filename format: {SUPPLIER}_{DATE}_{INVOICE_NUMBER}.pdf
                            $extension = pathinfo($data['invoice_file'], PATHINFO_EXTENSION);
                            $fileName = "{$supplierName}_{$invoiceDate}_{$invoiceNumber}.{$extension}";

                            // Local temp file path
                            $localInvoicePath = storage_path('app/public/' . $data['invoice_file']);

                            // Nextcloud remote path (Magazzino convention)
                            $nextcloudPath = "Magazzino/Fatture_Magazzino/Fornitori/{$year}/{$fileName}";

                            // Upload to Nextcloud
                            $nextcloudService = app(NextcloudService::class);
                            $uploaded = $nextcloudService->uploadDocument($localInvoicePath, $nextcloudPath);

                            if (!$uploaded) {
                                throw new \Exception("Errore caricamento fattura su Nextcloud");
                            }

                            // Delete temp file after successful upload
                            if (file_exists($localInvoicePath)) {
                                unlink($localInvoicePath);
                            }
                        }

                        // Prepare invoice data
                        $invoiceData = [
                            'invoice_number' => $data['invoice_number'],
                            'invoice_path' => $nextcloudPath, // Nextcloud path, not local
                            'invoice_date' => $data['invoice_date'],
                            'invoice_total' => $data['invoice_total'] ?? null,
                            'supplier' => $data['supplier'],
                            'notes' => $data['notes'] ?? null,
                            'project_id' => $data['project_id'] ?? null,
                        ];

                        // Get component file path (will be processed by Job)
                        $filePath = storage_path('app/public/' . $data['file']);

                        // Generate unique Job ID for tracking
                        $jobId = uniqid('import_', true);

                        // Dispatch Job for background processing
                        ImportComponentsJob::dispatch(
                            $filePath,
                            $data['supplier'],
                            null, // fieldMapping (auto-detect)
                            $invoiceData,
                            $jobId,
                            auth()->id()
                        );

                        // Notification with link to monitoring
                        Notification::make()
                            ->title('✅ Import avviato')
                            ->body("Clicca 'Monitor Import' per vedere il progresso in tempo reale")
                            ->success()
                            ->send();

                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Errore avvio import')
                            ->body("Error: {$e->getMessage()}")
                            ->danger()
                            ->send();

                        throw $e;
                    }
                }),
        ];
    }
    
}
