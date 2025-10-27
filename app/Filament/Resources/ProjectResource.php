<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectResource\Pages;
use App\Filament\Resources\ProjectResource\RelationManagers;
use App\Models\Project;
use App\Models\CompanyProfile;
use App\Services\DocumentService;
use App\Services\AiServiceFactory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Project Details')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Basic Information')
                            ->schema([
                                Forms\Components\TextInput::make('code')
                                    ->label('Project Code')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->placeholder('Auto-generated from name'),
                                    
                                Forms\Components\TextInput::make('name')
                                    ->label('Project Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        if (empty($state)) return;
                                        $code = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::slug($state, '-'));
                                        
                                        // Controlla se esiste già per mostrare l'anteprima corretta
                                        $existingProject = \App\Models\Project::where('code', $code)->first();
                                        if ($existingProject) {
                                            $code .= '-02 (o successivo)';
                                        }
                                        
                                        $set('code_preview', $code);
                                    }),
                                    
                                Forms\Components\Placeholder::make('code_preview')
                                    ->label('Generated Code Preview')
                                    ->content(fn ($get) => $get('code_preview') ?: 'Enter project name to see code'),
                                    
                                Forms\Components\Group::make([
                                    Forms\Components\Textarea::make('description')
                                        ->label('Descrizione Progetto')
                                        ->rows(4)
                                        ->columnSpanFull(),
                                    
                                    Forms\Components\Actions::make([
                                        Forms\Components\Actions\Action::make('improve_description_ai')
                                            ->label('✨ Migliora Descrizione con AI')
                                            ->color('info')
                                            ->visible(fn () => !empty(config('services.ai.provider')))
                                            ->action(function (Set $set, Get $get) {
                                                // Increase timeout for AI processing (Ollama can take 30-60 seconds)
                                                set_time_limit(120);

                                                $projectName = $get('name');
                                                $currentDescription = $get('description') ?? '';
                                                $customerId = $get('customer_id');

                                                if (empty($projectName)) {
                                                    Notification::make()
                                                        ->title('Nome Progetto Richiesto')
                                                        ->body('Inserisci prima il nome del progetto.')
                                                        ->warning()
                                                        ->send();
                                                    return;
                                                }

                                                if (empty(trim($currentDescription))) {
                                                    Notification::make()
                                                        ->title('Descrizione Richiesta')
                                                        ->body('Inserisci almeno una breve descrizione da migliorare.')
                                                        ->warning()
                                                        ->send();
                                                    return;
                                                }

                                                $aiService = AiServiceFactory::make();

                                                if (!$aiService->isConfigured()) {
                                                    Notification::make()
                                                        ->title('AI Non Configurata')
                                                        ->body('Configura l\'API key di Claude in Company Profile oppure installa Ollama per AI locale.')
                                                        ->warning()
                                                        ->send();
                                                    return;
                                                }

                                                try {
                                                    $customer = $customerId ? \App\Models\Customer::find($customerId) : null;

                                                    // Get quotation info if linked
                                                    $quotationInfo = '';
                                                    if ($record && $record->quotation) {
                                                        $quotationInfo = "Preventivo #{$record->quotation->number} - €" . number_format($record->quotation->total, 2);
                                                    }

                                                    $context = [
                                                        'customer_name' => $customer?->company_name ?? '',
                                                        'category' => 'Elettronica',
                                                        'budget' => $get('budget'),
                                                        'quotation' => $quotationInfo,
                                                        'due_date' => $get('due_date'),
                                                        'start_date' => $get('start_date'),
                                                        'boards_count' => $get('total_boards_ordered'),
                                                    ];

                                                    $improvedDescription = $aiService->improveProjectDescription(
                                                        $projectName,
                                                        $currentDescription,
                                                        $context
                                                    );

                                                    if ($improvedDescription) {
                                                        $set('description', $improvedDescription);

                                                        $provider = config('services.ai.provider') === 'ollama' ? 'Ollama' : 'Claude AI';
                                                        Notification::make()
                                                            ->title('Descrizione Migliorata')
                                                            ->body("La descrizione è stata migliorata da {$provider}. Puoi cliccare di nuovo per migliorarla ulteriormente.")
                                                            ->success()
                                                            ->duration(5000)
                                                            ->send();
                                                    } else {
                                                        throw new \Exception('Nessuna risposta dall\'AI');
                                                    }
                                                } catch (\Exception $e) {
                                                    Notification::make()
                                                        ->title('Errore AI')
                                                        ->body('Errore durante il miglioramento: ' . $e->getMessage())
                                                        ->danger()
                                                        ->send();
                                                }
                                            }),

                                        Forms\Components\Actions\Action::make('generate_milestones_ai')
                                            ->label('🤖 Genera Milestones con AI')
                                            ->color('success')
                                            ->requiresConfirmation()
                                            ->modalHeading('Generare milestone automaticamente?')
                                            ->modalDescription('L\'AI analizzerà la descrizione del progetto e genererà milestone appropriate per il settore elettronico.')
                                            ->modalIcon('heroicon-o-sparkles')
                                            ->visible(fn (Get $get) => !empty($get('description')) && !empty($get('name')))
                                            ->action(function (Get $get, Set $set, $record) {
                                                // Increase timeout for AI processing (Ollama can take 30-60 seconds)
                                                set_time_limit(120);

                                                $aiService = \App\Services\AiServiceFactory::make();

                                                if (!$aiService->isConfigured()) {
                                                    Notification::make()
                                                        ->title('AI Non Configurata')
                                                        ->body('Configura l\'API key di Claude in Company Profile oppure installa Ollama per AI locale.')
                                                        ->warning()
                                                        ->send();
                                                    return;
                                                }

                                                try {
                                                    $customer = $get('customer_id') ? \App\Models\Customer::find($get('customer_id')) : null;

                                                    // Get quotation info if linked
                                                    $quotationInfo = '';
                                                    if ($record && $record->quotation) {
                                                        $quotationTotal = number_format($record->quotation->total, 2);
                                                        $quotationInfo = "Preventivo #{$record->quotation->number} - €{$quotationTotal}";
                                                    }

                                                    $milestones = $aiService->generateProjectMilestones(
                                                        $get('name'),
                                                        $get('description'),
                                                        [
                                                            'customer' => $customer?->company_name,
                                                            'budget' => $get('budget'),
                                                            'due_date' => $get('due_date'),
                                                            'start_date' => $get('start_date'),
                                                            'quotation' => $quotationInfo,
                                                            'boards_count' => $get('total_boards_ordered'),
                                                            'status' => $get('status'),
                                                        ]
                                                    );

                                                    if (empty($milestones)) {
                                                        Notification::make()
                                                            ->title('Errore Generazione')
                                                            ->body('Impossibile generare milestone. Verifica la configurazione AI o riprova.')
                                                            ->danger()
                                                            ->send();
                                                        return;
                                                    }

                                                    // Create milestones and optionally attach to project
                                                    $createdMilestones = [];
                                                    $startDate = $get('start_date') ? \Carbon\Carbon::parse($get('start_date')) : now();

                                                    foreach ($milestones as $milestoneData) {
                                                        $milestone = \App\Models\Milestone::create([
                                                            'name' => $milestoneData['name'],
                                                            'description' => $milestoneData['description'],
                                                            'category' => $milestoneData['category'],
                                                            'color' => self::getCategoryColor($milestoneData['category']),
                                                            'is_active' => true,
                                                            'sort_order' => $milestoneData['sort_order'],
                                                        ]);

                                                        $createdMilestones[] = [
                                                            'id' => $milestone->id,
                                                            'target_date' => $startDate->copy()->addDays($milestoneData['deadline_offset_days']),
                                                            'sort_order' => $milestoneData['sort_order'],
                                                        ];
                                                    }

                                                    // If editing existing project, attach milestones
                                                    if ($record) {
                                                        foreach ($createdMilestones as $milestoneInfo) {
                                                            $record->milestones()->attach($milestoneInfo['id'], [
                                                                'target_date' => $milestoneInfo['target_date'],
                                                                'sort_order' => $milestoneInfo['sort_order'],
                                                                'is_completed' => false,
                                                            ]);
                                                        }
                                                    }

                                                    Notification::make()
                                                        ->title('Milestone Generate!')
                                                        ->body(count($milestones) . ' milestone create con successo dall\'AI.')
                                                        ->success()
                                                        ->duration(5000)
                                                        ->send();

                                                } catch (\Exception $e) {
                                                    \Illuminate\Support\Facades\Log::error('Failed to generate milestones', [
                                                        'error' => $e->getMessage(),
                                                        'trace' => $e->getTraceAsString()
                                                    ]);

                                                    Notification::make()
                                                        ->title('Errore Generazione Milestone')
                                                        ->body('Errore: ' . $e->getMessage())
                                                        ->danger()
                                                        ->send();
                                                }
                                            }),
                                    ])
                                    ->alignEnd()
                                    ->columnSpanFull(),
                                ])
                                ->columnSpanFull(),
                                    
                                Forms\Components\Select::make('customer_id')
                                    ->relationship('customer', 'company_name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->label('Customer Company'),
                                    
                                Forms\Components\Hidden::make('user_id')
                                    ->default(fn () => auth()->id()),
                            ])
                            ->columns(2),
                            
                        Forms\Components\Tabs\Tab::make('Project Management')
                            ->schema([
                                Forms\Components\Select::make('priority_id')
                                    ->label('Priority')
                                    ->relationship('priority', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\Select::make('color')
                                            ->options([
                                                'gray' => 'Gray',
                                                'yellow' => 'Yellow', 
                                                'orange' => 'Orange',
                                                'red' => 'Red',
                                                'green' => 'Green',
                                                'blue' => 'Blue',
                                            ])
                                            ->required(),
                                        Forms\Components\TextInput::make('sort_order')
                                            ->numeric()
                                            ->default(1),
                                    ])
                                    ->required(),
                                    
                                Forms\Components\Select::make('progress_id')
                                    ->label('Progress Status')
                                    ->relationship('progress', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('percentage')
                                            ->numeric()
                                            ->rules(['min:0', 'max:100'])
                                            ->default(0),
                                        Forms\Components\Select::make('color')
                                            ->options([
                                                'gray' => 'Gray',
                                                'blue' => 'Blue',
                                                'yellow' => 'Yellow',
                                                'orange' => 'Orange', 
                                                'green' => 'Green',
                                            ])
                                            ->required(),
                                    ])
                                    ->required(),
                                    
                                Forms\Components\Select::make('status')
                                    ->required()
                                    ->options([
                                        'planning' => 'Planning',
                                        'in_progress' => 'In Progress',
                                        'testing' => 'Testing',
                                        'consegna_prototipo_test' => 'Consegna Prototipo Test',
                                        'completed' => 'Completed',
                                        'on_hold' => 'On Hold',
                                        'cancelled' => 'Cancelled',
                                    ])
                                    ->default('planning'),
                                    
                                Forms\Components\Select::make('project_status')
                                    ->label('Project Status')
                                    ->options([
                                        'active' => 'Active',
                                        'archived' => 'Archived',
                                        'cancelled' => 'Cancelled',
                                    ])
                                    ->default('active'),
                            ])
                            ->columns(2),
                            
                        Forms\Components\Tabs\Tab::make('Timeline & Budget')
                            ->schema([
                                Forms\Components\DatePicker::make('start_date'),
                                Forms\Components\DatePicker::make('due_date'),
                                Forms\Components\DatePicker::make('completed_date'),

                                Forms\Components\Toggle::make('manual_budget')
                                    ->label('Budget Manuale')
                                    ->helperText('Se disattivato, il budget sarà calcolato automaticamente dai preventivi accettati')
                                    ->default(false)
                                    ->reactive()
                                    ->columnSpanFull(),

                                Forms\Components\Placeholder::make('auto_budget_info')
                                    ->label('Budget Automatico')
                                    ->content(function ($record) {
                                        if (!$record) {
                                            return 'Sarà calcolato dai preventivi accettati';
                                        }
                                        $autoBudget = $record->calculateBudgetFromQuotations();
                                        return '€ ' . number_format($autoBudget, 2, ',', '.');
                                    })
                                    ->visible(fn (Forms\Get $get) => !$get('manual_budget')),

                                Forms\Components\TextInput::make('budget')
                                    ->label('Budget')
                                    ->numeric()
                                    ->prefix('€')
                                    ->visible(fn (Forms\Get $get) => $get('manual_budget'))
                                    ->required(fn (Forms\Get $get) => $get('manual_budget')),

                                Forms\Components\TextInput::make('actual_cost')
                                    ->label('Costo Effettivo')
                                    ->numeric()
                                    ->prefix('€'),

                                Forms\Components\TextInput::make('folder')
                                    ->label('Project Folder')
                                    ->maxLength(255),

                                Forms\Components\Textarea::make('notes')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                            
                        Forms\Components\Tabs\Tab::make('Production Tracking')
                            ->schema([
                                Forms\Components\TextInput::make('total_boards_ordered')
                                    ->label('Total Boards Ordered')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Auto-calculated from linked quotations')
                                    ->suffixIcon('heroicon-o-calculator'),
                                    
                                Forms\Components\TextInput::make('boards_produced')
                                    ->label('Boards Produced')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Number of PCBs physically manufactured'),
                                    
                                Forms\Components\TextInput::make('boards_assembled')
                                    ->label('Boards Assembled')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Number of PCBs assembled and tested'),
                                    
                                Forms\Components\Placeholder::make('production_progress')
                                    ->label('Production Progress')
                                    ->content(fn ($get) => $get('total_boards_ordered') > 0 
                                        ? round(($get('boards_produced') / $get('total_boards_ordered')) * 100) . '%'
                                        : 'N/A'),
                                        
                                Forms\Components\Placeholder::make('assembly_progress')
                                    ->label('Assembly Progress')
                                    ->content(fn ($get) => $get('boards_produced') > 0 
                                        ? round(($get('boards_assembled') / $get('boards_produced')) * 100) . '%'
                                        : 'N/A'),
                            ])
                            ->columns(2),
                            
                        Forms\Components\Tabs\Tab::make('System Engineering')
                            ->schema([
                                Forms\Components\Placeholder::make('system_engineering_intro')
                                    ->label('System Engineering Overview')
                                    ->content('Gestisci i sistemi del progetto, le checklist di progettazione e il tracking del completamento.')
                                    ->columnSpanFull(),
                                    
                                Forms\Components\Placeholder::make('system_engineering_help')
                                    ->label('Come Gestire i Sistemi')
                                    ->content('I sistemi del progetto possono essere gestiti nella tab "Sistemi Progetto" qui sotto. Puoi aggiungere sistemi, tracciare il completamento delle checklist e monitorare il progress.')
                                    ->columnSpanFull(),
                                
                                // Progress summary (read-only)
                                Forms\Components\Placeholder::make('systems_summary')
                                    ->label('Sistemi Attivi')
                                    ->content(function ($record) {
                                        if (!$record) return 'Nessun sistema configurato';
                                        
                                        $instances = \App\Models\ProjectSystemInstance::where('project_id', $record->id)
                                            ->with('systemVariant.category')
                                            ->active()
                                            ->get();
                                            
                                        if ($instances->isEmpty()) {
                                            return 'Nessun sistema configurato per questo progetto';
                                        }
                                        
                                        $summary = $instances->map(function ($instance) {
                                            return $instance->instance_name . ' (' . $instance->completion_percentage . '% completato)';
                                        })->join(', ');
                                        
                                        return $summary;
                                    })
                                    ->columnSpanFull(),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Linked Documents & Quotations')
                            ->schema([
                                Forms\Components\Select::make('quotations')
                                    ->label('Linked Quotations')
                                    ->relationship('quotations', 'number')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Link existing quotations to this project'),
                                    
                                Forms\Components\Placeholder::make('documents_info')
                                    ->label('Project Documents')
                                    ->content('Documents can be managed in the Project Documents section. Upload KiCad files, Gerbers, BOMs, invoices, and other project-related files.')
                                    ->columnSpanFull(),
                                    
                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make('view_documents')
                                        ->label('Manage Documents')
                                        ->icon('heroicon-o-document-text')
                                        ->url(fn ($record) => $record ? route('filament.admin.resources.project-documents.index', ['tableFilters[project_id][value]' => $record->id]) : '#')
                                        ->openUrlInNewTab()
                                        ->visible(fn ($record) => $record?->exists),
                                        
                                    Forms\Components\Actions\Action::make('generate_datasheet')
                                        ->label('Genera Datasheet')
                                        ->icon('heroicon-o-clipboard-document-list')
                                        ->color('success')
                                        ->action(function ($record) {
                                            if (!$record) return;
                                            
                                            // Trova template predefinito per progetti
                                            $template = \App\Models\DatasheetTemplate::where('type', 'project')
                                                ->where('is_default', true)
                                                ->where('is_active', true)
                                                ->first();
                                                
                                            if (!$template) {
                                                \Filament\Notifications\Notification::make()
                                                    ->title('Template non trovato')
                                                    ->body('Nessun template predefinito per progetti. Creane uno in Template Datasheet.')
                                                    ->warning()
                                                    ->send();
                                                return;
                                            }
                                            
                                            try {
                                                $generator = app(\App\Services\DatasheetGeneratorService::class);
                                                $generated = $generator->generate($record, $template);
                                                
                                                \Filament\Notifications\Notification::make()
                                                    ->title('Datasheet generato')
                                                    ->body("Datasheet generato: {$generated->title}")
                                                    ->success()
                                                    ->actions([
                                                        \Filament\Notifications\Actions\Action::make('download')
                                                            ->label('Download')
                                                            ->url(route('datasheets.download', $generated->id))
                                                            ->openUrlInNewTab(),
                                                    ])
                                                    ->send();
                                            } catch (\Exception $e) {
                                                \Filament\Notifications\Notification::make()
                                                    ->title('Errore generazione')
                                                    ->body("Errore: {$e->getMessage()}")
                                                    ->danger()
                                                    ->send();
                                            }
                                        })
                                        ->visible(fn ($record) => $record?->exists),
                                        
                                    Forms\Components\Actions\Action::make('analyze_compliance')
                                        ->label('🤖 Analisi Conformità AI')
                                        ->icon('heroicon-o-shield-check')
                                        ->color('info')
                                        ->action(function ($record) {
                                            if (!$record) return;
                                            
                                            try {
                                                $complianceService = app(\App\Services\ComplianceAiService::class);
                                                $analysis = $complianceService->analyzeCompliance($record);
                                                
                                                $summary = $analysis->getSummaryAttribute();
                                                
                                                \Filament\Notifications\Notification::make()
                                                    ->title('Analisi Conformità Completata')
                                                    ->body("Standard rilevati: {$summary['total_standards']}, Raccomandazioni: {$summary['total_recommendations']}, Confidenza: {$summary['confidence_level']}")
                                                    ->success()
                                                    ->actions([
                                                        \Filament\Notifications\Actions\Action::make('view_analysis')
                                                            ->label('Visualizza Dettagli')
                                                            ->url(route('filament.admin.resources.compliance-ai-analyses.view', $analysis->id))
                                                            ->openUrlInNewTab(),
                                                    ])
                                                    ->duration(10000)
                                                    ->send();
                                                    
                                                // Mostra avviso se ci sono azioni urgenti
                                                if ($summary['needs_action']) {
                                                    \Filament\Notifications\Notification::make()
                                                        ->title('⚠️ Azioni Richieste')
                                                        ->body('L\'analisi ha rilevato standard obbligatori o raccomandazioni ad alta priorità.')
                                                        ->warning()
                                                        ->persistent()
                                                        ->send();
                                                }
                                                
                                            } catch (\Exception $e) {
                                                \Filament\Notifications\Notification::make()
                                                    ->title('Errore Analisi Conformità')
                                                    ->body("Errore: {$e->getMessage()}")
                                                    ->danger()
                                                    ->send();
                                            }
                                        })
                                        ->visible(fn ($record) => $record?->exists && \App\Models\CompanyProfile::current()->isClaudeEnabled()),
                                        
                                    Forms\Components\Actions\Action::make('generate_user_manual')
                                        ->label('📖 Genera Manuale Utente')
                                        ->icon('heroicon-o-document-text')
                                        ->color('success')
                                        ->visible(fn ($record) => $record?->exists && \App\Models\CompanyProfile::current()->isClaudeEnabled())
                                        ->form([
                                            Forms\Components\Select::make('type')
                                                ->label('Tipo Manuale')
                                                ->options(\App\Models\UserManual::getTypeOptions())
                                                ->required()
                                                ->default('operation'),
                                                
                                            Forms\Components\Select::make('format')
                                                ->label('Formato')
                                                ->options(\App\Models\UserManual::getFormatOptions())
                                                ->required()
                                                ->default('pdf'),
                                                
                                            Forms\Components\TextInput::make('version')
                                                ->label('Versione')
                                                ->default('1.0')
                                                ->required(),
                                                
                                            Forms\Components\Textarea::make('custom_prompt')
                                                ->label('Prompt Personalizzato (Opzionale)')
                                                ->rows(4)
                                                ->helperText('Istruzioni specifiche per la generazione del manuale'),
                                        ])
                                        ->action(function ($record, array $data, \App\Services\UserManualGeneratorService $generator) {
                                            try {
                                                $manual = $generator->generateManual($record, [
                                                    'type' => $data['type'],
                                                    'format' => $data['format'],
                                                    'version' => $data['version'],
                                                    'custom_prompt' => $data['custom_prompt'] ?? null,
                                                ]);
                                                
                                                \Filament\Notifications\Notification::make()
                                                    ->title('Generazione Manuale Avviata')
                                                    ->body("Manuale '{$manual->title}' in generazione. Controlla la sezione User Manuals per il progresso.")
                                                    ->success()
                                                    ->actions([
                                                        \Filament\Notifications\Actions\Action::make('view_manual')
                                                            ->label('Visualizza Manuale')
                                                            ->url(route('filament.admin.resources.user-manuals.edit', $manual->id))
                                                            ->openUrlInNewTab(),
                                                    ])
                                                    ->duration(8000)
                                                    ->send();
                                                    
                                            } catch (\Exception $e) {
                                                \Filament\Notifications\Notification::make()
                                                    ->title('Errore Generazione Manuale')
                                                    ->body("Errore: {$e->getMessage()}")
                                                    ->danger()
                                                    ->send();
                                            }
                                        }),
                                ])
                                ->columnSpanFull(),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Notifiche Email')
                            ->schema([
                                Forms\Components\Toggle::make('email_notifications')
                                    ->label('Abilita Notifiche Email')
                                    ->helperText('Attiva l\'invio automatico di notifiche email per le scadenze del progetto')
                                    ->default(true),
                                    
                                Forms\Components\TextInput::make('client_email')
                                    ->label('Email Cliente')
                                    ->email()
                                    ->helperText('Email del cliente per ricevere le notifiche di scadenza')
                                    ->maxLength(255)
                                    ->visible(fn ($get) => $get('email_notifications')),
                                    
                                Forms\Components\TextInput::make('notification_days_before')
                                    ->label('Giorni Prima della Scadenza')
                                    ->numeric()
                                    ->default(7)
                                    ->minValue(1)
                                    ->maxValue(30)
                                    ->helperText('Numero di giorni prima della scadenza per inviare la notifica')
                                    ->visible(fn ($get) => $get('email_notifications')),
                                    
                                Forms\Components\DatePicker::make('end_date')
                                    ->label('Data Scadenza Progetto')
                                    ->helperText('Data di scadenza finale del progetto per calcolare le notifiche')
                                    ->visible(fn ($get) => $get('email_notifications')),
                                    
                                Forms\Components\Placeholder::make('last_notification_info')
                                    ->label('Ultima Notifica Inviata')
                                    ->content(fn ($record) => $record?->last_notification_sent 
                                        ? $record->last_notification_sent->format('d/m/Y H:i')
                                        : 'Nessuna notifica inviata')
                                    ->visible(fn ($record) => $record?->exists),
                                    
                                Forms\Components\Section::make('Test Notifiche')
                                    ->description('Invia email di test per verificare la configurazione')
                                    ->schema([
                                        Forms\Components\Actions::make([
                                            Forms\Components\Actions\Action::make('send_test_notification')
                                                ->label('📧 Invia Email di Test')
                                                ->color('warning')
                                                ->visible(fn ($record, $get) => $record?->exists && $get('email_notifications') && $get('client_email'))
                                                ->action(function ($record, Get $get, \App\Services\NotificationService $notificationService) {
                                                    try {
                                                        $success = $notificationService->sendTestEmail($get('client_email'));
                                                        
                                                        if ($success) {
                                                            Notification::make()
                                                                ->title('Email di Test Inviata')
                                                                ->body('Email di test inviata con successo a ' . $get('client_email'))
                                                                ->success()
                                                                ->send();
                                                        } else {
                                                            throw new \Exception('Invio fallito');
                                                        }
                                                    } catch (\Exception $e) {
                                                        Notification::make()
                                                            ->title('Errore Invio Email')
                                                            ->body('Errore durante l\'invio: ' . $e->getMessage())
                                                            ->danger()
                                                            ->send();
                                                    }
                                                }),
                                        ])
                                        ->columnSpanFull(),
                                    ])
                                    ->collapsed()
                                    ->visible(fn ($record) => $record?->exists),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Project Code')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                    
                Tables\Columns\TextColumn::make('name')
                    ->label('Project Name')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                    
                Tables\Columns\TextColumn::make('customer.company_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('manager.name')
                    ->label('Project Manager')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('priority.name')
                    ->label('Priority')
                    ->badge()
                    ->color(fn ($record) => $record->priority?->color ?? 'gray')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('progress.name')
                    ->label('Progress')
                    ->badge()
                    ->color(fn ($record) => $record->progress?->color ?? 'gray')
                    ->description(fn ($record) => $record->progress ? $record->progress->percentage . '%' : '')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('milestone_completion_percentage')
                    ->label('Milestone')
                    ->formatStateUsing(fn ($record) => 
                        $record->total_milestones_count > 0 
                            ? "{$record->completed_milestones_count}/{$record->total_milestones_count}"
                            : 'Nessuna'
                    )
                    ->description(fn ($record) => 
                        $record->total_milestones_count > 0 
                            ? $record->milestone_completion_percentage . '%'
                            : ''
                    )
                    ->badge()
                    ->color(fn ($record) => match(true) {
                        $record->milestone_completion_percentage === 100.0 => 'success',
                        $record->milestone_completion_percentage >= 75 => 'info',
                        $record->milestone_completion_percentage >= 50 => 'warning',
                        $record->milestone_completion_percentage > 0 => 'danger',
                        default => 'gray',
                    }),
                    
                Tables\Columns\TextColumn::make('total_boards_ordered')
                    ->label('Boards Ordered')
                    ->sortable()
                    ->toggleable()
                    ->placeholder('0'),
                    
                Tables\Columns\TextColumn::make('boards_produced')
                    ->label('Produced')
                    ->sortable()
                    ->toggleable()
                    ->description(fn ($record) => $record->total_boards_ordered > 0 
                        ? round(($record->boards_produced / $record->total_boards_ordered) * 100) . '%'
                        : '')
                    ->placeholder('0'),
                    
                Tables\Columns\TextColumn::make('boards_assembled')
                    ->label('Assembled')
                    ->sortable()
                    ->toggleable()
                    ->description(fn ($record) => $record->boards_produced > 0 
                        ? round(($record->boards_assembled / $record->boards_produced) * 100) . '%'
                        : '')
                    ->placeholder('0'),
                Tables\Columns\TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('budget')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('actual_cost')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('project_status')
                    ->label('Project Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'archived' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('folder')
                    ->label('Folder')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('generate_ddt')
                    ->label('Generate DDT')
                    ->icon('heroicon-o-document-text')
                    ->visible(fn (Project $record): bool => $record->status === 'consegna_prototipo_test')
                    ->action(function (Project $record) {
                        $documentService = app(DocumentService::class);

                        try {
                            $pdfPath = $documentService->generateDdtPdf($record);

                            Notification::make()
                                ->title('DDT generated successfully')
                                ->body("PDF saved to: {$pdfPath}")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Failed to generate DDT')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('approve_test_board')
                    ->label('Approva Prima Scheda Test')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approva Prima Scheda Test')
                    ->modalDescription('La prima scheda prodotta è stata testata con successo? Questo la segnerà come scheda consegnata e completerà la milestone di testing.')
                    ->visible(fn (Project $record): bool =>
                        $record->boards_produced >= 1 &&
                        $record->boards_assembled == 0 &&
                        $record->status !== 'completed'
                    )
                    ->action(function (Project $record) {
                        try {
                            // Increment boards_assembled to mark first board as delivered
                            $record->update(['boards_assembled' => 1]);

                            // Complete testing milestone if exists
                            $testingMilestone = $record->milestones()
                                ->where('category', 'testing')
                                ->wherePivot('is_completed', false)
                                ->first();

                            if ($testingMilestone) {
                                $testingMilestone->pivot->is_completed = true;
                                $testingMilestone->pivot->completed_date = now();
                                $testingMilestone->pivot->save();

                                // Recalculate completion percentage
                                $record->updateCompletionPercentage();
                            }

                            Notification::make()
                                ->title('Prima Scheda Approvata')
                                ->body('La prima scheda test è stata approvata e segnata come consegnata.')
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Errore')
                                ->body('Errore durante l\'approvazione: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
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
            RelationManagers\MilestonesRelationManager::class,
            RelationManagers\SystemInstancesRelationManager::class,
            RelationManagers\ProjectDocumentsRelationManager::class,
            RelationManagers\BoardAssemblyLogsRelationManager::class,
            RelationManagers\ProjectBomsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
        ];
    }

    /**
     * Get color for milestone category.
     */
    protected static function getCategoryColor(string $category): string
    {
        return match ($category) {
            'design' => 'blue',
            'prototyping' => 'purple',
            'testing' => 'yellow',
            'production' => 'orange',
            'delivery' => 'green',
            'documentation' => 'gray',
            default => 'blue',
        };
    }
}
