<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Modulo con filtri -->
        <form wire:submit="save">
            {{ $this->form }}
        </form>
        
        <!-- Statistiche inventario -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <x-filament::section>
                <x-slot name="heading">
                    📱 Componenti Elettronici
                </x-slot>
                
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Totale componenti:</span>
                        <span class="font-semibold">{{ \App\Models\Component::count() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Attivi:</span>
                        <span class="font-semibold text-green-600">{{ \App\Models\Component::where('status', 'active')->count() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Stock basso:</span>
                        <span class="font-semibold text-red-600">{{ \App\Models\Component::lowStock()->count() }}</span>
                    </div>
                </div>
            </x-filament::section>
            
            <x-filament::section>
                <x-slot name="heading">
                    🧪 Materiali
                </x-slot>
                
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Totale materiali:</span>
                        <span class="font-semibold">{{ \App\Models\Material::count() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Attivi:</span>
                        <span class="font-semibold text-green-600">{{ \App\Models\Material::where('status', 'active')->count() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Stock basso:</span>
                        <span class="font-semibold text-red-600">{{ \App\Models\Material::lowStock()->count() }}</span>
                    </div>
                </div>
            </x-filament::section>
            
            <x-filament::section>
                <x-slot name="heading">
                    🔧 Attrezzature
                </x-slot>
                
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Totale attrezzature:</span>
                        <span class="font-semibold">{{ \App\Models\Equipment::count() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">In uso:</span>
                        <span class="font-semibold text-green-600">{{ \App\Models\Equipment::where('status', 'active')->count() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Necessitano manutenzione:</span>
                        <span class="font-semibold text-orange-600">{{ \App\Models\Equipment::needsMaintenance()->count() }}</span>
                    </div>
                </div>
            </x-filament::section>
        </div>
        
        <!-- Informazioni sui formati export -->
        <x-filament::section>
            <x-slot name="heading">
                ℹ️ Informazioni Export
            </x-slot>
            
            <div class="prose max-w-none text-sm">
                <ul>
                    <li><strong>Export Solo Componenti:</strong> Esporta tutti i componenti elettronici con SKU, prezzi, stock e informazioni fornitore</li>
                    <li><strong>Export Solo Materiali:</strong> Esporta filamenti 3D, resine, cancelleria e materiali di consumo</li>
                    <li><strong>Export Solo Attrezzature:</strong> Esporta computer, strumenti, macchinari con info manutenzione e calibrazione</li>
                    <li><strong>Export Inventario Completo:</strong> File Excel con 3 fogli separati contenente tutto l'inventario</li>
                </ul>
                
                <p class="mt-4"><strong>Formato file:</strong> I file vengono esportati in formato Excel (.xlsx) con data e ora nel nome file per evitare sovrascrizioni.</p>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>