<div wire:poll.1s="loadProgress">
    @if($progress['status'] === 'processing')
        <div class="space-y-2">
            <div class="flex justify-between text-sm">
                <span class="font-medium">Import in corso...</span>
                <span class="text-gray-600">{{ $progress['percentage'] }}%</span>
            </div>

            <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden">
                <div class="bg-blue-600 h-4 transition-all duration-300 ease-out"
                     style="width: {{ $progress['percentage'] }}%">
                </div>
            </div>

            <div class="text-sm text-gray-600">
                {{ $progress['message'] }}
            </div>

            @if(isset($progress['current']) && isset($progress['total']))
                <div class="text-xs text-gray-500">
                    {{ $progress['current'] }} / {{ $progress['total'] }} componenti
                </div>
            @endif
        </div>
    @elseif($progress['status'] === 'completed')
        <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-green-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                </svg>
                <span class="font-semibold text-green-800">Import completato!</span>
            </div>

            @if(isset($progress['result']))
                <div class="mt-2 text-sm text-green-700">
                    Importati: {{ $progress['result']['imported'] ?? 0 }} |
                    Aggiornati: {{ $progress['result']['updated'] ?? 0 }} |
                    Falliti: {{ $progress['result']['failed'] ?? 0 }}
                </div>

                @if(isset($progress['result']['invoice_saved']) && $progress['result']['invoice_saved'])
                    <div class="mt-2 text-sm text-green-700">
                        Fattura collegata con {{ $progress['result']['movements_created'] ?? 0 }} movimenti di inventario
                    </div>
                @endif
            @endif

            <div class="mt-4">
                <a href="/admin/components" class="text-blue-600 hover:underline font-medium">
                    Vai ai Componenti
                </a>
            </div>
        </div>
    @elseif($progress['status'] === 'failed')
        <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-red-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/>
                </svg>
                <span class="font-semibold text-red-800">Import fallito</span>
            </div>

            @if(isset($progress['error']))
                <div class="mt-2 text-sm text-red-700">
                    {{ $progress['error'] }}
                </div>
            @endif

            <div class="mt-4">
                <a href="/admin/components" class="text-blue-600 hover:underline font-medium">
                    Torna ai Componenti
                </a>
            </div>
        </div>
    @else
        <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-yellow-600 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="font-semibold text-yellow-800">Caricamento stato import...</span>
            </div>
        </div>
    @endif
</div>
