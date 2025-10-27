<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerContractResource\Pages;
use App\Models\CustomerContract;
use App\Services\PdfGeneratorService;
use App\Services\ContractAnalysisService;
use App\Services\ContractReviewService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\HtmlString;

class CustomerContractResource extends Resource
{
    protected static ?string $model = CustomerContract::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Contratti Clienti';

    protected static ?string $modelLabel = 'Contratto Cliente';

    protected static ?string $pluralModelLabel = 'Contratti Clienti';

    protected static ?string $navigationGroup = 'Clienti';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informazioni Contratto')
                    ->schema([
                        Forms\Components\TextInput::make('contract_number')
                            ->label('Numero Contratto')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Auto-generato: CTR-YYYY-XXX')
                            ->columnSpan(1),

                        Forms\Components\Select::make('customer_id')
                            ->label('Cliente')
                            ->relationship('customer', 'company_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('title')
                            ->label('Titolo Contratto')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        Forms\Components\Select::make('type')
                            ->label('Tipo Contratto')
                            ->options([
                                'nda' => 'NDA (Non-Disclosure Agreement)',
                                'service_agreement' => 'Contratto di Servizio',
                                'supply_contract' => 'Contratto di Fornitura',
                                'partnership' => 'Partnership',
                            ])
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\Select::make('status')
                            ->label('Stato')
                            ->options([
                                'draft' => 'Bozza',
                                'active' => 'Attivo',
                                'expired' => 'Scaduto',
                                'terminated' => 'Terminato',
                            ])
                            ->default('draft')
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Date e Importi')
                    ->schema([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Data Inizio')
                            ->required()
                            ->default(now())
                            ->columnSpan(1),

                        Forms\Components\DatePicker::make('end_date')
                            ->label('Data Fine')
                            ->helperText('Lasciare vuoto per contratti a tempo indeterminato')
                            ->columnSpan(1),

                        Forms\Components\DatePicker::make('signed_at')
                            ->label('Data Firma')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('contract_value')
                            ->label('Valore Contratto (€)')
                            ->numeric()
                            ->prefix('€')
                            ->columnSpan(1),

                        Forms\Components\Select::make('currency')
                            ->label('Valuta')
                            ->options([
                                'EUR' => 'Euro (EUR)',
                                'USD' => 'Dollaro USA (USD)',
                                'GBP' => 'Sterlina (GBP)',
                            ])
                            ->default('EUR')
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Termini e Note')
                    ->schema([
                        Forms\Components\RichEditor::make('terms')
                            ->label('Termini e Condizioni')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'bulletList',
                                'orderedList',
                                'h2',
                                'h3',
                            ])
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Note Interne')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Analisi AI')
                    ->schema([
                        Forms\Components\Placeholder::make('ai_analyzed_status')
                            ->label('Stato Analisi')
                            ->content(function ($record) {
                                if (!$record || !$record->isAnalyzed()) {
                                    return 'Non ancora analizzato';
                                }
                                return 'Analizzato il ' . $record->ai_analyzed_at->format('d/m/Y H:i');
                            })
                            ->columnSpan(1),

                        Forms\Components\Placeholder::make('ai_risk_summary')
                            ->label('Riepilogo Rischi')
                            ->content(function ($record) {
                                if (!$record || !$record->isAnalyzed()) {
                                    return '-';
                                }

                                $riskCounts = $record->getRiskCountBySeverity();
                                $parts = [];

                                if ($riskCounts['alta'] > 0) {
                                    $parts[] = "Alta: {$riskCounts['alta']}";
                                }
                                if ($riskCounts['media'] > 0) {
                                    $parts[] = "Media: {$riskCounts['media']}";
                                }
                                if ($riskCounts['bassa'] > 0) {
                                    $parts[] = "Bassa: {$riskCounts['bassa']}";
                                }

                                return empty($parts) ? 'Nessun rischio identificato' : implode(' | ', $parts);
                            })
                            ->columnSpan(1),

                        Forms\Components\Placeholder::make('ai_parties_count')
                            ->label('Parti Coinvolte')
                            ->content(function ($record) {
                                if (!$record || !$record->isAnalyzed()) {
                                    return '-';
                                }
                                return count($record->ai_extracted_parties ?? []);
                            })
                            ->columnSpan(1),

                        Forms\Components\Placeholder::make('ai_dates_count')
                            ->label('Date Chiave')
                            ->content(function ($record) {
                                if (!$record || !$record->isAnalyzed()) {
                                    return '-';
                                }
                                return count($record->ai_key_dates ?? []);
                            })
                            ->columnSpan(1),

                        Forms\Components\ViewField::make('ai_extracted_parties_details')
                            ->label('Parti Coinvolte - Dettaglio')
                            ->view('filament.forms.components.contract-parties-list')
                            ->visible(fn ($record) => $record && $record->isAnalyzed() && !empty($record->ai_extracted_parties))
                            ->columnSpanFull(),

                        Forms\Components\ViewField::make('ai_key_dates_details')
                            ->label('Date Chiave - Dettaglio')
                            ->view('filament.forms.components.contract-dates-list')
                            ->visible(fn ($record) => $record && $record->isAnalyzed() && !empty($record->ai_key_dates))
                            ->columnSpanFull(),

                        Forms\Components\ViewField::make('ai_risk_flags_details')
                            ->label('Clausole Rischiose - Dettaglio')
                            ->view('filament.forms.components.contract-risks-list')
                            ->visible(fn ($record) => $record && $record->isAnalyzed() && !empty($record->ai_risk_flags))
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('ai_analysis_summary')
                            ->label('Riassunto Generale')
                            ->default(fn ($record) => $record?->ai_analysis_data['riassunto_generale'] ?? null)
                            ->disabled()
                            ->rows(4)
                            ->visible(fn ($record) => $record && $record->isAnalyzed() && !empty($record->ai_analysis_data['riassunto_generale']))
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record && $record->isAnalyzed())
                    ->collapsible()
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('contract_number')
                    ->label('Numero')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('customer.company_name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('title')
                    ->label('Titolo')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->title),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'nda' => 'warning',
                        'service_agreement' => 'success',
                        'supply_contract' => 'info',
                        'partnership' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'nda' => 'NDA',
                        'service_agreement' => 'Servizio',
                        'supply_contract' => 'Fornitura',
                        'partnership' => 'Partnership',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Inizio')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Fine')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('Indeterminato'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'active' => 'success',
                        'expired' => 'warning',
                        'terminated' => 'danger',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Bozza',
                        'active' => 'Attivo',
                        'expired' => 'Scaduto',
                        'terminated' => 'Terminato',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('contract_value')
                    ->label('Valore')
                    ->money('EUR')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('ai_review_score')
                    ->label('Review AI')
                    ->badge()
                    ->color(fn ($record) => $record->isReviewed() ? $record->review_score_color : 'gray')
                    ->formatStateUsing(fn ($record) => $record->isReviewed() ? $record->ai_review_score . '/100' : 'Non revisionato')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Cliente')
                    ->relationship('customer', 'company_name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'nda' => 'NDA',
                        'service_agreement' => 'Servizio',
                        'supply_contract' => 'Fornitura',
                        'partnership' => 'Partnership',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Stato')
                    ->options([
                        'draft' => 'Bozza',
                        'active' => 'Attivo',
                        'expired' => 'Scaduto',
                        'terminated' => 'Terminato',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),

                Tables\Actions\Action::make('analyze_with_ai')
                    ->label('Analizza con AI')
                    ->icon('heroicon-o-sparkles')
                    ->color('info')
                    ->visible(fn (CustomerContract $record) => !empty($record->nextcloud_path))
                    ->action(function (CustomerContract $record) {
                        $analysisService = app(ContractAnalysisService::class);

                        try {
                            // Ottieni il path del PDF
                            $pdfPath = $analysisService->getContractPdfPath($record);

                            if (!$pdfPath) {
                                Notification::make()
                                    ->title('PDF Non Trovato')
                                    ->body('Il file PDF del contratto non è stato trovato.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            // Esegui l'analisi
                            $analysisData = $analysisService->analyzeContractPdf($record, $pdfPath);

                            // Salva i risultati
                            $record->update($analysisData);

                            // Genera riassunto
                            $summary = $analysisService->generateAnalysisSummary($analysisData);

                            Notification::make()
                                ->title('Analisi Completata')
                                ->body($summary)
                                ->success()
                                ->duration(10000)
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Errore Analisi AI')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Analizza Contratto con AI')
                    ->modalDescription('Il contratto verrà analizzato automaticamente da Claude AI per estrarre date, importi, parti coinvolte e clausole rischiose.')
                    ->modalSubmitActionLabel('Analizza')
                    ->modalIcon('heroicon-o-sparkles'),

                Tables\Actions\Action::make('review_with_ai')
                    ->label('Revisiona con AI')
                    ->icon('heroicon-o-shield-check')
                    ->color('warning')
                    ->action(function (CustomerContract $record) {
                        $reviewService = app(ContractReviewService::class);

                        try {
                            $result = $reviewService->reviewContract($record);

                            if (!$result['success']) {
                                Notification::make()
                                    ->title('Errore Revisione')
                                    ->body($result['error'])
                                    ->danger()
                                    ->send();
                                return;
                            }

                            $record->update([
                                'ai_review_data' => $result['review_data'],
                                'ai_review_score' => $result['score'],
                                'ai_review_issues_count' => $result['issues_count'],
                                'ai_reviewed_at' => $result['reviewed_at'],
                            ]);

                            Notification::make()
                                ->title('Revisione Completata')
                                ->body("Score: {$result['score']}/100 - Problemi: {$result['issues_count']}")
                                ->success()
                                ->duration(5000)
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Errore Revisione AI')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Revisiona Contratto con AI')
                    ->modalDescription('Il contratto verrà revisionato da Claude AI per verificare clausole mancanti, rischi legali e compliance normativa italiana.')
                    ->modalSubmitActionLabel('Revisiona')
                    ->modalIcon('heroicon-o-shield-check'),

                Tables\Actions\Action::make('view_review')
                    ->label('Vedi Revisione')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->color('info')
                    ->visible(fn (CustomerContract $record) => $record->isReviewed())
                    ->modalHeading(fn (CustomerContract $record) => "Revisione: {$record->contract_number}")
                    ->modalContent(fn (CustomerContract $record) => static::renderReviewModal($record))
                    ->modalWidth('5xl')
                    ->slideOver(),

                Tables\Actions\Action::make('generate_pdf')
                    ->label('Genera PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(function (CustomerContract $record) {
                        $pdfService = app(PdfGeneratorService::class);

                        try {
                            $pdfService->generateCustomerContractPdf($record, uploadToNextcloud: true);

                            Notification::make()
                                ->title('PDF Generato')
                                ->body("PDF generato e caricato su Nextcloud per contratto {$record->contract_number}")
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Errore Generazione PDF')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Genera PDF Contratto')
                    ->modalDescription('Verrà generato il PDF e caricato automaticamente su Nextcloud.')
                    ->modalSubmitActionLabel('Genera'),

                Tables\Actions\Action::make('download_pdf')
                    ->label('Scarica PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->action(function (CustomerContract $record) {
                        $pdfService = app(PdfGeneratorService::class);
                        return $pdfService->downloadCustomerContractPdf($record);
                    })
                    ->visible(fn (CustomerContract $record) => $record->pdf_generated_at !== null),

                Tables\Actions\Action::make('upload_signed')
                    ->label('Upload PDF Firmato')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('success')
                    ->action(function ($record) {
                        // TODO: Implement signed PDF upload
                        Notification::make()
                            ->title('Upload PDF')
                            ->body('Funzionalità in sviluppo - upload PDF firmato')
                            ->info()
                            ->send();
                    }),

                Tables\Actions\Action::make('send_for_signature')
                    ->label('Invia per Firma Elettronica')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status === 'draft')
                    ->action(function ($record) {
                        // TODO: Implement electronic signature sending
                        Notification::make()
                            ->title('Firma Elettronica')
                            ->body('Funzionalità in sviluppo - invio per firma elettronica')
                            ->info()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerContracts::route('/'),
            'create' => Pages\CreateCustomerContract::route('/create'),
            'edit' => Pages\EditCustomerContract::route('/{record}/edit'),
        ];
    }

    protected static function renderReviewModal(CustomerContract $record): HtmlString
    {
        $reviewData = $record->ai_review_data;
        $reviewService = new ContractReviewService();

        $html = '<div style="font-family: sans-serif;">';

        // Overall Score
        $scoreColor = match (true) {
            $record->ai_review_score >= 80 => '#10b981',
            $record->ai_review_score >= 60 => '#f59e0b',
            default => '#ef4444',
        };

        $html .= "<div style='padding: 16px; background: {$scoreColor}20; border-left: 4px solid {$scoreColor}; margin-bottom: 24px; border-radius: 4px;'>";
        $html .= "<div style='font-size: 24px; font-weight: bold; color: {$scoreColor};'>Score: {$record->ai_review_score}/100</div>";
        $html .= "<div style='color: #666; margin-top: 4px;'>Problemi identificati: {$record->ai_review_issues_count}</div>";
        $html .= "<div style='color: #666; margin-top: 4px;'>Revisionato il: " . $record->ai_reviewed_at->format('d/m/Y H:i') . "</div>";
        $html .= "</div>";

        // Overall Assessment
        if (isset($reviewData['overall_assessment'])) {
            $assessment = $reviewData['overall_assessment'];
            $html .= "<div style='margin-bottom: 24px;'>";
            $html .= "<h3 style='font-size: 18px; font-weight: bold; margin-bottom: 12px;'>Valutazione Generale</h3>";
            $html .= "<p style='color: #666; margin-bottom: 12px;'>{$assessment['quality']}</p>";

            if (!empty($assessment['strengths'])) {
                $html .= "<div style='margin-bottom: 12px;'><strong>Punti di Forza:</strong>";
                $html .= "<ul style='margin: 8px 0; padding-left: 24px;'>";
                foreach ($assessment['strengths'] as $strength) {
                    $html .= "<li style='color: #10b981; margin: 4px 0;'>{$strength}</li>";
                }
                $html .= "</ul></div>";
            }

            if (!empty($assessment['weaknesses'])) {
                $html .= "<div><strong>Punti Deboli:</strong>";
                $html .= "<ul style='margin: 8px 0; padding-left: 24px;'>";
                foreach ($assessment['weaknesses'] as $weakness) {
                    $html .= "<li style='color: #ef4444; margin: 4px 0;'>{$weakness}</li>";
                }
                $html .= "</ul></div>";
            }
            $html .= "</div>";
        }

        // Checklist Results
        if (isset($reviewData['checklist_results'])) {
            $checklist = $reviewService->getChecklistForType($record->type);
            $html .= "<div style='margin-bottom: 24px;'>";
            $html .= "<h3 style='font-size: 18px; font-weight: bold; margin-bottom: 12px;'>Checklist Clausole</h3>";

            foreach ($reviewData['checklist_results'] as $key => $result) {
                $status = $result['status'];
                $statusColors = [
                    'present' => ['bg' => '#10b98120', 'text' => '#10b981', 'icon' => '&#10004;', 'label' => 'Presente'],
                    'needs_improvement' => ['bg' => '#f59e0b20', 'text' => '#f59e0b', 'icon' => '&#9888;', 'label' => 'Da Migliorare'],
                    'missing' => ['bg' => '#ef444420', 'text' => '#ef4444', 'icon' => '&#10008;', 'label' => 'Mancante'],
                ];

                $color = $statusColors[$status] ?? $statusColors['missing'];
                $label = $checklist[$key]['label'] ?? $key;

                $html .= "<div style='padding: 12px; background: {$color['bg']}; border-left: 4px solid {$color['text']}; margin-bottom: 12px; border-radius: 4px;'>";
                $html .= "<div style='display: flex; align-items: center; margin-bottom: 4px;'>";
                $html .= "<span style='color: {$color['text']}; font-size: 18px; margin-right: 8px;'>{$color['icon']}</span>";
                $html .= "<span style='font-weight: bold; color: {$color['text']};'>{$label}</span>";
                $html .= "<span style='margin-left: auto; padding: 2px 8px; background: {$color['text']}20; color: {$color['text']}; border-radius: 12px; font-size: 12px;'>{$color['label']}</span>";
                $html .= "</div>";

                if (!empty($result['comment'])) {
                    $html .= "<div style='color: #666; font-size: 14px; margin-top: 8px;'>{$result['comment']}</div>";
                }

                if (!empty($result['suggestion'])) {
                    $html .= "<div style='color: #666; font-size: 14px; margin-top: 8px; font-style: italic;'><strong>Suggerimento:</strong> {$result['suggestion']}</div>";
                }

                if (!empty($result['suggested_text'])) {
                    $html .= "<div style='margin-top: 8px; padding: 8px; background: #f3f4f6; border-radius: 4px; font-size: 13px;'>";
                    $html .= "<strong>Testo suggerito:</strong><br>";
                    $html .= nl2br(htmlspecialchars($result['suggested_text']));
                    $html .= "</div>";
                }
                $html .= "</div>";
            }
            $html .= "</div>";
        }

        // Legal Risks
        if (!empty($reviewData['legal_risks'])) {
            $html .= "<div style='margin-bottom: 24px;'>";
            $html .= "<h3 style='font-size: 18px; font-weight: bold; margin-bottom: 12px; color: #ef4444;'>&#9888; Rischi Legali</h3>";

            foreach ($reviewData['legal_risks'] as $risk) {
                $severityColors = [
                    'critical' => '#dc2626',
                    'high' => '#ea580c',
                    'medium' => '#f59e0b',
                    'low' => '#64748b',
                ];
                $severityColor = $severityColors[$risk['severity']] ?? $severityColors['medium'];

                $html .= "<div style='padding: 12px; background: {$severityColor}20; border-left: 4px solid {$severityColor}; margin-bottom: 12px; border-radius: 4px;'>";
                $html .= "<div style='font-weight: bold; color: {$severityColor}; margin-bottom: 4px;'>{$risk['title']}</div>";
                $html .= "<div style='color: #666; font-size: 14px; margin-bottom: 8px;'>{$risk['description']}</div>";
                $html .= "<div style='color: #666; font-size: 14px;'><strong>Raccomandazione:</strong> {$risk['recommendation']}</div>";
                $html .= "</div>";
            }
            $html .= "</div>";
        }

        // Compliance Issues
        if (!empty($reviewData['compliance_issues'])) {
            $html .= "<div style='margin-bottom: 24px;'>";
            $html .= "<h3 style='font-size: 18px; font-weight: bold; margin-bottom: 12px; color: #7c3aed;'>&#9878; Problemi di Compliance</h3>";

            foreach ($reviewData['compliance_issues'] as $issue) {
                $html .= "<div style='padding: 12px; background: #7c3aed20; border-left: 4px solid #7c3aed; margin-bottom: 12px; border-radius: 4px;'>";
                $html .= "<div style='font-weight: bold; color: #7c3aed; margin-bottom: 4px;'>{$issue['regulation']}</div>";
                if (!empty($issue['article'])) {
                    $html .= "<div style='color: #666; font-size: 13px; margin-bottom: 4px;'>Articolo: {$issue['article']}</div>";
                }
                $html .= "<div style='color: #666; font-size: 14px; margin-bottom: 8px;'>{$issue['issue']}</div>";
                $html .= "<div style='color: #666; font-size: 14px;'><strong>Soluzione:</strong> {$issue['remedy']}</div>";
                $html .= "</div>";
            }
            $html .= "</div>";
        }

        // Improvements
        if (!empty($reviewData['improvements'])) {
            $html .= "<div>";
            $html .= "<h3 style='font-size: 18px; font-weight: bold; margin-bottom: 12px;'>&#128161; Miglioramenti Suggeriti</h3>";

            foreach ($reviewData['improvements'] as $improvement) {
                $priorityColors = [
                    'high' => '#ef4444',
                    'medium' => '#f59e0b',
                    'low' => '#64748b',
                ];
                $priorityColor = $priorityColors[$improvement['priority']] ?? $priorityColors['medium'];

                $html .= "<div style='padding: 12px; background: #f3f4f6; border-left: 4px solid {$priorityColor}; margin-bottom: 12px; border-radius: 4px;'>";
                $html .= "<div style='font-weight: bold; margin-bottom: 4px;'>{$improvement['area']}</div>";
                $html .= "<div style='color: #666; font-size: 14px; margin-bottom: 8px;'><strong>Attuale:</strong> {$improvement['current']}</div>";
                $html .= "<div style='padding: 8px; background: white; border-radius: 4px; font-size: 13px;'>";
                $html .= "<strong>Suggerito:</strong><br>";
                $html .= nl2br(htmlspecialchars($improvement['suggested']));
                $html .= "</div>";
                $html .= "</div>";
            }
            $html .= "</div>";
        }

        $html .= '</div>';

        return new HtmlString($html);
    }
}
