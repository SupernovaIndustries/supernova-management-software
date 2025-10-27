<div class="space-y-3">
    @if($getRecord() && $getRecord()->ai_risk_flags)
        @foreach($getRecord()->ai_risk_flags as $risk)
            @php
                $severity = $risk['gravita'] ?? 'bassa';
                $bgColor = match($severity) {
                    'alta' => 'bg-red-50 dark:bg-red-950 border-red-200 dark:border-red-800',
                    'media' => 'bg-yellow-50 dark:bg-yellow-950 border-yellow-200 dark:border-yellow-800',
                    default => 'bg-blue-50 dark:bg-blue-950 border-blue-200 dark:border-blue-800',
                };
                $badgeColor = match($severity) {
                    'alta' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                    'media' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                    default => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                };
            @endphp

            <div class="{{ $bgColor }} rounded-lg p-4 border">
                <div class="flex items-start justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeColor }}">
                            {{ strtoupper($severity) }}
                        </span>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ ucfirst($risk['tipo'] ?? 'N/A') }}
                        </span>
                    </div>
                </div>

                <div class="space-y-2">
                    <div>
                        <div class="text-sm font-semibold text-gray-900 dark:text-white mb-1">Descrizione:</div>
                        <div class="text-sm text-gray-700 dark:text-gray-300">
                            {{ $risk['descrizione'] ?? 'N/A' }}
                        </div>
                    </div>

                    @if(!empty($risk['testo_originale']))
                        <div class="mt-2">
                            <div class="text-sm font-semibold text-gray-900 dark:text-white mb-1">Testo Originale:</div>
                            <div class="text-xs text-gray-600 dark:text-gray-400 italic bg-white dark:bg-gray-900 p-2 rounded border border-gray-200 dark:border-gray-700">
                                "{{ $risk['testo_originale'] }}"
                            </div>
                        </div>
                    @endif

                    @if(!empty($risk['raccomandazioni']))
                        <div class="mt-2">
                            <div class="text-sm font-semibold text-gray-900 dark:text-white mb-1">Raccomandazioni:</div>
                            <div class="text-sm text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-900 p-2 rounded border border-gray-200 dark:border-gray-700">
                                {{ $risk['raccomandazioni'] }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">Nessuna clausola rischiosa identificata</p>
    @endif
</div>
