<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Form per inserire part numbers -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">
                📦 Import Web Order da Mouser
            </h3>
            
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                Inserisci i part numbers Mouser che vuoi ordinare. Il sistema li cercherà automaticamente e creerà un web order nel tuo account Mouser.
            </p>
            
            {{ $this->form }}
        </div>

        <!-- Risultati ricerca -->
        @if(!empty($this->searchResults))
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">
                🔍 Risultati Ricerca ({{ count($this->searchResults) }} componenti)
            </h3>
            
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="text-left py-2 px-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Select</th>
                            <th class="text-left py-2 px-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Part Number</th>
                            <th class="text-left py-2 px-3 text-sm font-semibold text-gray-700 dark:text-gray-300">MPN</th>
                            <th class="text-left py-2 px-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Descrizione</th>
                            <th class="text-left py-2 px-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Stock</th>
                            <th class="text-left py-2 px-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Prezzo</th>
                            <th class="text-left py-2 px-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->searchResults as $index => $part)
                        <tr class="border-b border-gray-100 dark:border-gray-700 {{ $part['error'] ?? false ? 'bg-red-50 dark:bg-red-900/20' : '' }}">
                            <td class="py-3 px-3">
                                @if(!($part['error'] ?? false))
                                    <input 
                                        type="checkbox" 
                                        wire:model="searchResults.{{ $index }}.selected"
                                        class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                        checked
                                    >
                                @else
                                    <span class="text-red-500">❌</span>
                                @endif
                            </td>
                            
                            <td class="py-3 px-3">
                                <span class="font-mono text-sm {{ $part['error'] ?? false ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">
                                    {{ $part['part_number'] }}
                                </span>
                            </td>
                            
                            <td class="py-3 px-3">
                                <span class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ $part['manufacturer_part_number'] ?? 'N/A' }}
                                </span>
                            </td>
                            
                            <td class="py-3 px-3">
                                <div class="max-w-xs">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">
                                        {{ $part['description'] ?? 'N/A' }}
                                    </span>
                                </div>
                            </td>
                            
                            <td class="py-3 px-3">
                                <span class="text-sm font-medium {{ $part['error'] ?? false ? 'text-red-600' : 'text-green-600' }}">
                                    {{ $part['stock'] ?? 'Unknown' }}
                                </span>
                            </td>
                            
                            <td class="py-3 px-3">
                                @if(!empty($part['price_breaks']))
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                                        €{{ number_format($part['price_breaks'][0]['price'] ?? 0, 4) }}
                                    </span>
                                @else
                                    <span class="text-sm text-gray-500">N/A</span>
                                @endif
                            </td>
                            
                            <td class="py-3 px-3">
                                @if(!($part['error'] ?? false))
                                    <input 
                                        type="number" 
                                        wire:model="searchResults.{{ $index }}.quantity"
                                        min="1" 
                                        value="{{ $part['quantity'] ?? 1 }}"
                                        class="w-20 rounded border-gray-300 text-sm focus:ring-primary-500"
                                    >
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            @if(count(array_filter($this->searchResults, fn($r) => !($r['error'] ?? false))) > 0)
            <div class="mt-4 p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                <p class="text-sm text-green-700 dark:text-green-300">
                    ✅ {{ count(array_filter($this->searchResults, fn($r) => !($r['error'] ?? false))) }} componenti pronti per l'ordine
                </p>
            </div>
            @endif
            
            @if(count(array_filter($this->searchResults, fn($r) => $r['error'] ?? false)) > 0)
            <div class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                <p class="text-sm text-red-700 dark:text-red-300">
                    ❌ {{ count(array_filter($this->searchResults, fn($r) => $r['error'] ?? false)) }} componenti non trovati su Mouser
                </p>
            </div>
            @endif
        </div>
        @endif
    </div>
</x-filament-panels::page>