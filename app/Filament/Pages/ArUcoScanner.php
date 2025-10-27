<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Services\ArUcoService;
use App\Models\Component;
use Filament\Notifications\Notification;

class ArUcoScanner extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-camera';
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'ArUco Scanner';
    protected static ?int $navigationSort = 5;
    protected static string $view = 'filament.pages.aruco-scanner';
    
    public ?Component $scannedComponent = null;
    public string $scannedCode = '';
    
    protected function getViewData(): array
    {
        return [
            'scannedComponent' => $this->scannedComponent,
        ];
    }
    
    public function scanCode(string $code): void
    {
        $this->scannedCode = $code;
        $service = app(ArUcoService::class);
        
        $component = $service->findByArUcoCode($code);
        
        if ($component) {
            $this->scannedComponent = $component;
            
            Notification::make()
                ->title('Component Found')
                ->body($component->name . ' - ' . $component->manufacturer_part_number)
                ->success()
                ->send();
        } else {
            $this->scannedComponent = null;
            
            Notification::make()
                ->title('Component Not Found')
                ->body('No component found with ArUco code: ' . $code)
                ->danger()
                ->send();
        }
    }
    
    public function clearScan(): void
    {
        $this->scannedComponent = null;
        $this->scannedCode = '';
    }
}