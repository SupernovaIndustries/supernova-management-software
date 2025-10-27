<x-filament-panels::page>
    <div wire:poll.2s="refresh" class="space-y-4">
        @if(empty($importJobs))
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-8 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Nessun import in corso</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Carica un CSV per iniziare un nuovo import</p>
            </div>
        @else
            @foreach($importJobs as $job)
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-3">
                                @if($job['status'] === 'processing')
                                    <div class="animate-spin h-5 w-5 border-2 border-primary-600 border-t-transparent rounded-full"></div>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Import in corso...</h3>
                                @elseif($job['status'] === 'completed')
                                    <svg class="h-6 w-6 text-success-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <h3 class="text-lg font-semibold text-success-700 dark:text-success-400">Import completato</h3>
                                @elseif($job['status'] === 'failed')
                                    <svg class="h-6 w-6 text-danger-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <h3 class="text-lg font-semibold text-danger-700 dark:text-danger-400">Import fallito</h3>
                                @endif

                                <span class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ $job['job_id'] }}</span>
                            </div>

                            @if($job['status'] === 'processing')
                                <div class="mt-4">
                                    <div class="flex justify-between text-sm mb-2">
                                        <span class="text-gray-700 dark:text-gray-300">{{ $job['message'] ?? 'Elaborazione...' }}</span>
                                        <span class="font-semibold text-primary-600">{{ $job['percentage'] ?? 0 }}%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 overflow-hidden">
                                        <div class="bg-primary-600 h-3 rounded-full transition-all duration-300 ease-out"
                                             style="width: {{ $job['percentage'] ?? 0 }}%">
                                        </div>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">
                                            <span class="font-medium">{{ $job['current'] ?? 0 }}</span> /
                                            <span class="font-medium">{{ $job['total'] ?? 0 }}</span> componenti
                                        </span>
                                        <a href="/admin/import-progress/{{ $job['job_id'] }}"
                                           target="_blank"
                                           class="text-xs text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 font-medium inline-flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            Vedi Log Real-time
                                        </a>
                                    </div>
                                </div>
                            @endif

                            @if($job['status'] === 'completed' && isset($job['result']))
                                <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div class="bg-success-50 dark:bg-success-950 rounded-lg p-3">
                                        <div class="text-2xl font-bold text-success-700 dark:text-success-400">{{ $job['result']['imported'] ?? 0 }}</div>
                                        <div class="text-xs text-success-600 dark:text-success-500">Importati</div>
                                    </div>
                                    <div class="bg-primary-50 dark:bg-primary-950 rounded-lg p-3">
                                        <div class="text-2xl font-bold text-primary-700 dark:text-primary-400">{{ $job['result']['updated'] ?? 0 }}</div>
                                        <div class="text-xs text-primary-600 dark:text-primary-500">Aggiornati</div>
                                    </div>
                                    <div class="bg-warning-50 dark:bg-warning-950 rounded-lg p-3">
                                        <div class="text-2xl font-bold text-warning-700 dark:text-warning-400">{{ $job['result']['skipped'] ?? 0 }}</div>
                                        <div class="text-xs text-warning-600 dark:text-warning-500">Saltati</div>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-3">
                                        <div class="text-2xl font-bold text-gray-700 dark:text-gray-300">
                                            {{ ($job['result']['imported'] ?? 0) + ($job['result']['updated'] ?? 0) + ($job['result']['skipped'] ?? 0) }}
                                        </div>
                                        <div class="text-xs text-gray-600 dark:text-gray-500">Totale</div>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center justify-between">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        Completato {{ \Carbon\Carbon::parse($job['completed_at'])->diffForHumans() }}
                                    </span>
                                    <a href="/admin/import-progress/{{ $job['job_id'] }}"
                                       target="_blank"
                                       class="text-xs text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 font-medium inline-flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        Vedi Log Dettagliati
                                    </a>
                                </div>
                            @endif

                            @if($job['status'] === 'failed' && isset($job['error']))
                                <div class="mt-4 bg-danger-50 dark:bg-danger-950 border border-danger-200 dark:border-danger-800 rounded-lg p-4">
                                    <p class="text-sm text-danger-800 dark:text-danger-200 font-mono">{{ $job['error'] }}</p>
                                </div>
                                <div class="mt-3 flex items-center justify-between">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        Fallito {{ \Carbon\Carbon::parse($job['failed_at'])->diffForHumans() }}
                                    </span>
                                    <a href="/admin/import-progress/{{ $job['job_id'] }}"
                                       target="_blank"
                                       class="text-xs text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 font-medium inline-flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        Vedi Log Dettagliati
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</x-filament-panels::page>
