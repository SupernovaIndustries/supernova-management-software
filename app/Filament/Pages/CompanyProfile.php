<?php

namespace App\Filament\Pages;

use App\Models\CompanyProfile as CompanyProfileModel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Support\Exceptions\Halt;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class CompanyProfile extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static string $view = 'filament.pages.company-profile';
    protected static ?string $navigationLabel = 'Profilo Aziendale';
    protected static ?string $title = 'Profilo Aziendale';
    protected static ?string $navigationGroup = 'Amministrazione';
    protected static ?int $navigationSort = 1;

    public ?array $data = [];
    public CompanyProfileModel $profile;

    public function mount(): void
    {
        $this->profile = CompanyProfileModel::current();
        $this->form->fill($this->profile->toArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dati Aziendali')
                    ->description('Informazioni sempre visibili dell\'azienda')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('owner_name')
                                    ->label('Nome Proprietario')
                                    ->required()
                                    ->maxLength(255),
                                
                                Forms\Components\TextInput::make('owner_title')
                                    ->label('Titolo/Ruolo')
                                    ->required()
                                    ->maxLength(255),
                            ]),
                        
                        Forms\Components\TextInput::make('company_name')
                            ->label('Ragione Sociale')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('vat_number')
                                    ->label('Partita IVA')
                                    ->required()
                                    ->maxLength(255),
                                
                                Forms\Components\TextInput::make('tax_code')
                                    ->label('Codice Fiscale')
                                    ->required()
                                    ->maxLength(255),
                                
                                Forms\Components\TextInput::make('sdi_code')
                                    ->label('Codice SDI')
                                    ->required()
                                    ->maxLength(255),
                            ]),
                    ])
                    ->collapsible()
                    ->persistCollapsed(false),

                Forms\Components\Section::make('Sede Legale')
                    ->schema([
                        Forms\Components\TextInput::make('legal_address')
                            ->label('Indirizzo')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\Grid::make(4)
                            ->schema([
                                Forms\Components\TextInput::make('legal_city')
                                    ->label('Città')
                                    ->required()
                                    ->maxLength(255),
                                
                                Forms\Components\TextInput::make('legal_province')
                                    ->label('Provincia')
                                    ->required()
                                    ->maxLength(10),
                                
                                Forms\Components\TextInput::make('legal_postal_code')
                                    ->label('CAP')
                                    ->required()
                                    ->maxLength(10),
                                
                                Forms\Components\TextInput::make('legal_country')
                                    ->label('Paese')
                                    ->required()
                                    ->maxLength(255),
                            ]),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Contatti')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255),
                                
                                Forms\Components\TextInput::make('phone')
                                    ->label('Telefono')
                                    ->tel()
                                    ->maxLength(255),
                            ]),
                        
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('website')
                                    ->label('Sito Web')
                                    ->url()
                                    ->maxLength(255),
                                
                                Forms\Components\TextInput::make('pec')
                                    ->label('PEC')
                                    ->email()
                                    ->maxLength(255),
                            ]),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Integrazione AI')
                    ->description('Configurazione AI per il miglioramento automatico delle descrizioni')
                    ->schema([
                        Forms\Components\Toggle::make('claude_enabled')
                            ->label('Abilita AI')
                            ->helperText('Attiva l\'integrazione AI per migliorare descrizioni progetti e milestone')
                            ->reactive(),

                        Forms\Components\Select::make('claude_model')
                            ->label('Modello AI')
                            ->options(CompanyProfileModel::getClaudeModels())
                            ->helperText('Seleziona Claude API o modello Ollama locale')
                            ->required()
                            ->visible(fn (Get $get): bool => $get('claude_enabled')),

                        Forms\Components\TextInput::make('ollama_url')
                            ->label('URL Ollama')
                            ->helperText('URL del server Ollama locale (es: http://localhost:11434)')
                            ->default('http://localhost:11434')
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $get('claude_enabled') && str_contains($get('claude_model') ?? '', 'llama')),

                        Forms\Components\TextInput::make('claude_api_key')
                            ->label('API Key Claude')
                            ->password()
                            ->revealable()
                            ->helperText('Necessaria solo per modelli Claude API')
                            ->maxLength(500)
                            ->visible(fn (Get $get): bool => $get('claude_enabled') && str_contains($get('claude_model') ?? '', 'claude')),

                        Forms\Components\Toggle::make('auto_generate_milestones')
                            ->label('Auto-genera Milestone con AI')
                            ->helperText('Genera automaticamente milestone quando crei un nuovo progetto con descrizione')
                            ->visible(fn (Get $get): bool => $get('claude_enabled'))
                            ->default(false),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Configurazione Email')
                    ->description('Impostazioni SMTP per invio notifiche automatiche')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('smtp_host')
                                    ->label('Server SMTP')
                                    ->maxLength(255),
                                
                                Forms\Components\TextInput::make('smtp_port')
                                    ->label('Porta SMTP')
                                    ->numeric()
                                    ->default(587),
                            ]),
                        
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('smtp_username')
                                    ->label('Username SMTP')
                                    ->maxLength(255),
                                
                                Forms\Components\TextInput::make('smtp_password')
                                    ->label('Password SMTP')
                                    ->password()
                                    ->revealable()
                                    ->maxLength(255),
                            ]),
                        
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('smtp_encryption')
                                    ->label('Crittografia')
                                    ->options(CompanyProfileModel::getSmtpEncryptionOptions())
                                    ->required(),
                                
                                Forms\Components\TextInput::make('mail_from_address')
                                    ->label('Email Mittente')
                                    ->email()
                                    ->maxLength(255),
                                
                                Forms\Components\TextInput::make('mail_from_name')
                                    ->label('Nome Mittente')
                                    ->maxLength(255),
                            ]),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Documenti Aziendali')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\FileUpload::make('logo_path')
                                    ->label('Logo Aziendale')
                                    ->image()
                                    ->imageEditor()
                                    ->directory('company')
                                    ->maxSize(2048),
                                
                                Forms\Components\FileUpload::make('letterhead_path')
                                    ->label('Carta Intestata')
                                    ->image()
                                    ->directory('company')
                                    ->maxSize(2048),
                            ]),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Tariffe e Costi Standard')
                    ->description('Configurazione tariffe orarie e costi standard per le quotazioni')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('hourly_rate_design')
                                    ->label('Tariffa Oraria Progettazione')
                                    ->numeric()
                                    ->prefix('€')
                                    ->step(0.01)
                                    ->default(50.00)
                                    ->helperText('Tariffa oraria per ore di progettazione'),
                                
                                Forms\Components\TextInput::make('hourly_rate_assembly')
                                    ->label('Tariffa Oraria Assemblaggio')
                                    ->numeric()
                                    ->prefix('€')
                                    ->step(0.01)
                                    ->default(50.00)
                                    ->helperText('Tariffa oraria per ore di assemblaggio'),
                            ]),
                        
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('pcb_standard_cost')
                                    ->label('Costo Standard PCB')
                                    ->numeric()
                                    ->prefix('€')
                                    ->step(0.01)
                                    ->default(200.00)
                                    ->helperText('Costo standard per produzione PCB + spedizione'),
                                
                                Forms\Components\TextInput::make('pcb_standard_quantity')
                                    ->label('Quantità Standard PCB')
                                    ->numeric()
                                    ->default(5)
                                    ->helperText('Numero standard di schede incluse nel costo'),
                            ]),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Note')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Note Aggiuntive')
                            ->rows(3),
                    ])
                    ->collapsible(),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('💾 Salva Profilo')
                ->action('save')
                ->color('success')
                ->icon('heroicon-o-check'),
                
            Action::make('test_claude')
                ->label('🤖 Test Claude AI')
                ->action('testClaude')
                ->color('info')
                ->icon('heroicon-o-cpu-chip')
                ->visible(fn (): bool => $this->profile->isClaudeEnabled()),
                
            Action::make('test_email')
                ->label('📧 Test Email')
                ->action('testEmail')
                ->color('warning')
                ->icon('heroicon-o-envelope')
                ->visible(fn (): bool => $this->profile->isEmailConfigured()),
        ];
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();
            $this->profile->fill($data);
            $this->profile->save();

            Notification::make()
                ->title('Profilo Salvato')
                ->success()
                ->body('Il profilo aziendale è stato aggiornato con successo.')
                ->send();
        } catch (Halt $exception) {
            return;
        }
    }

    public function testClaude(): void
    {
        if (!$this->profile->isClaudeEnabled()) {
            Notification::make()
                ->title('Claude AI Non Configurato')
                ->danger()
                ->body('Configura prima l\'API key di Claude AI.')
                ->send();
            return;
        }

        try {
            $claudeService = app(\App\Services\ClaudeAiService::class);
            $result = $claudeService->testConnection();
            
            if ($result['success']) {
                Notification::make()
                    ->title('Test Claude AI - Successo')
                    ->success()
                    ->body('Connessione a Claude AI funzionante! Risposta: ' . $result['response'])
                    ->send();
            } else {
                Notification::make()
                    ->title('Test Claude AI - Fallito')
                    ->danger()
                    ->body($result['message'])
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Errore Test Claude')
                ->danger()
                ->body('Errore durante il test: ' . $e->getMessage())
                ->send();
        }
    }

    public function testEmail(): void
    {
        if (!$this->profile->isEmailConfigured()) {
            Notification::make()
                ->title('Email Non Configurata')
                ->danger()
                ->body('Completa prima la configurazione SMTP.')
                ->send();
            return;
        }

        try {
            $notificationService = app(\App\Services\NotificationService::class);
            
            // Usa l'email del profilo, se non c'è usa l'email mittente
            $testEmail = $this->profile->email ?: $this->profile->mail_from_address;
            
            if (empty($testEmail)) {
                Notification::make()
                    ->title('Email Non Configurata')
                    ->danger()
                    ->body('Inserisci un\'email nel profilo aziendale o nell\'email mittente.')
                    ->send();
                return;
            }
            
            $success = $notificationService->sendTestEmail($testEmail);
            
            if ($success) {
                Notification::make()
                    ->title('Test Email - Successo')
                    ->success()
                    ->body("Email di test inviata con successo a: {$testEmail}")
                    ->send();
            } else {
                Notification::make()
                    ->title('Test Email - Fallito')
                    ->danger()
                    ->body('Impossibile inviare l\'email di test.')
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Errore Test Email')
                ->danger()
                ->body('Errore durante il test: ' . $e->getMessage())
                ->send();
        }
    }
}