<div class="space-y-2">
    @if($getRecord() && $getRecord()->ai_key_dates)
        @foreach($getRecord()->ai_key_dates as $date)
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-900 dark:text-white">
                                {{ isset($date['data']) ? \Carbon\Carbon::parse($date['data'])->format('d/m/Y') : 'Data non specificata' }}
                            </span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                {{ ($date['tipo'] ?? '') === 'scadenza' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' }}">
                                {{ $date['tipo'] ?? 'N/A' }}
                            </span>
                        </div>
                        @if(!empty($date['descrizione']))
                            <div class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                                {{ $date['descrizione'] }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">Nessuna data chiave identificata</p>
    @endif
</div>
