<div class="space-y-2">
    @if($getRecord() && $getRecord()->ai_extracted_parties)
        @foreach($getRecord()->ai_extracted_parties as $party)
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="font-semibold text-gray-900 dark:text-white">
                            {{ $party['nome'] ?? 'N/A' }}
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                {{ $party['ruolo'] ?? 'N/A' }}
                            </span>
                        </div>
                        @if(!empty($party['dettagli']))
                            <div class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                                {{ $party['dettagli'] }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">Nessuna parte coinvolta identificata</p>
    @endif
</div>
