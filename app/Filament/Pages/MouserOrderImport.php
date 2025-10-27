<?php

namespace App\Filament\Pages;

use App\Services\Suppliers\MouserApiService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;

class MouserOrderImport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static string $view = 'filament.pages.mouser-order-import';
    protected static ?string $navigationGroup = 'Suppliers';
    protected static ?string $title = 'Import Mouser Order';
    
    public string $partNumbers = '';
    public string $customerOrderNumber = '';
    public array $searchResults = [];
    public array $selectedParts = [];
    
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('customerOrderNumber')
                    ->label('Numero Ordine Cliente')
                    ->placeholder('ORD-2024-001')
                    ->required()
                    ->maxLength(100),
                    
                Textarea::make('partNumbers')
                    ->label('Part Numbers Mouser')
                    ->placeholder('Inserisci i part numbers uno per riga, es:
595-TLV9001IDCKT
511-STM32F103C8T6
710-686674280002')
                    ->rows(8)
                    ->required()
                    ->helperText('Inserisci un part number Mouser per riga'),
            ]);
    }
    
    protected function getHeaderActions(): array
    {
        return [
            Action::make('searchParts')
                ->label('🔍 Cerca Part Numbers')
                ->color('primary')
                ->action('searchMouserParts'),
                
            Action::make('createOrder')
                ->label('📋 Crea Web Order')
                ->color('success')
                ->visible(fn() => !empty($this->searchResults))
                ->action('createMouserOrder'),
        ];
    }
    
    public function searchMouserParts(): void
    {
        $this->validate([
            'partNumbers' => 'required|string',
        ]);
        
        $partNumbers = array_filter(
            array_map('trim', explode("\n", $this->partNumbers))
        );
        
        if (empty($partNumbers)) {
            Notification::make()
                ->title('Errore')
                ->body('Inserisci almeno un part number')
                ->danger()
                ->send();
            return;
        }
        
        $mouseService = new MouserApiService();
        $this->searchResults = [];
        
        foreach ($partNumbers as $partNumber) {
            $partDetails = $mouseService->getPartDetails($partNumber);
            
            if ($partDetails) {
                $this->searchResults[] = array_merge($partDetails, [
                    'requested_part_number' => $partNumber,
                    'quantity' => 1,
                    'selected' => true
                ]);
            } else {
                $this->searchResults[] = [
                    'requested_part_number' => $partNumber,
                    'part_number' => $partNumber,
                    'manufacturer_part_number' => 'Not Found',
                    'description' => 'Part number non trovato su Mouser',
                    'stock' => 'Unknown',
                    'price_breaks' => [],
                    'quantity' => 1,
                    'selected' => false,
                    'error' => true
                ];
            }
        }
        
        Notification::make()
            ->title('Ricerca Completata')
            ->body('Trovati ' . count(array_filter($this->searchResults, fn($r) => !($r['error'] ?? false))) . ' componenti su ' . count($partNumbers))
            ->success()
            ->send();
    }
    
    public function createMouserOrder(): void
    {
        $this->validate([
            'customerOrderNumber' => 'required|string|max:100',
        ]);
        
        if (empty($this->searchResults)) {
            Notification::make()
                ->title('Errore')
                ->body('Nessun componente trovato per creare l\'ordine')
                ->danger()
                ->send();
            return;
        }
        
        $validParts = array_filter($this->searchResults, function($part) {
            return ($part['selected'] ?? false) && !($part['error'] ?? false);
        });
        
        if (empty($validParts)) {
            Notification::make()
                ->title('Errore')
                ->body('Seleziona almeno un componente valido')
                ->danger()
                ->send();
            return;
        }
        
        $mouseService = new MouserApiService();
        $orderItems = array_map(function($part) {
            return [
                'part_number' => $part['part_number'],
                'quantity' => $part['quantity'],
                'customer_part_number' => $part['manufacturer_part_number'] ?? ''
            ];
        }, $validParts);
        
        $cartKey = $mouseService->createWebOrder($orderItems, $this->customerOrderNumber);
        
        if ($cartKey) {
            Notification::make()
                ->title('✅ Web Order Creato!')
                ->body("Cart Key: {$cartKey}")
                ->success()
                ->persistent()
                ->send();
                
            // Reset form
            $this->reset(['partNumbers', 'customerOrderNumber', 'searchResults']);
        } else {
            Notification::make()
                ->title('❌ Errore Creazione Ordine')
                ->body('Controlla i logs per maggiori dettagli')
                ->danger()
                ->send();
        }
    }
}