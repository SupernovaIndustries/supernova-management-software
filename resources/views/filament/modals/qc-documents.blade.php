<div class="space-y-4">
    @if($documents->isEmpty())
        <div class="text-center py-8">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Nessun documento QC</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Non sono stati caricati documenti QC per questo assemblaggio.</p>
        </div>
    @else
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 mb-4">
            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">
                Informazioni Assemblaggio
            </h4>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Data:</dt>
                    <dd class="text-gray-900 dark:text-gray-100 font-medium">{{ $assemblyLog->assembly_date->format('d/m/Y') }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">N. Schede:</dt>
                    <dd class="text-gray-900 dark:text-gray-100 font-medium">{{ $assemblyLog->boards_count }}</dd>
                </div>
                @if($assemblyLog->batch_number)
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Batch:</dt>
                    <dd class="text-gray-900 dark:text-gray-100 font-medium">{{ $assemblyLog->batch_number }}</dd>
                </div>
                @endif
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Stato:</dt>
                    <dd>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            @if($assemblyLog->status === 'tested') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                            @elseif($assemblyLog->status === 'assembled') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                            @elseif($assemblyLog->status === 'failed') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                            @elseif($assemblyLog->status === 'rework') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                            @endif">
                            {{ ucfirst($assemblyLog->status) }}
                        </span>
                    </dd>
                </div>
            </dl>
        </div>

        <div class="space-y-3">
            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                Documenti QC ({{ $documents->count() }})
            </h4>

            @foreach($documents as $document)
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-start space-x-3 flex-1">
                        <div class="flex-shrink-0">
                            @if(str_starts_with($document->mime_type, 'image/'))
                                <svg class="h-8 w-8 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            @elseif($document->mime_type === 'application/pdf')
                                <svg class="h-8 w-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                            @else
                                <svg class="h-8 w-8 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                {{ $document->title }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {{ number_format($document->file_size / 1024, 2) }} KB
                                @if($document->created_at)
                                    • Caricato il {{ $document->created_at->format('d/m/Y H:i') }}
                                @endif
                            </p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1 font-mono truncate">
                                {{ $document->file_path }}
                            </p>
                        </div>
                    </div>

                    <div class="flex-shrink-0 ml-4">
                        @if(str_starts_with($document->mime_type, 'image/'))
                        <button
                            type="button"
                            onclick="window.open('{{ $document->url }}', '_blank')"
                            class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 shadow-sm text-xs font-medium rounded text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        >
                            <svg class="-ml-0.5 mr-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            Visualizza
                        </button>
                        @else
                        <a
                            href="{{ $document->url }}"
                            target="_blank"
                            class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 shadow-sm text-xs font-medium rounded text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        >
                            <svg class="-ml-0.5 mr-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Download
                        </a>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        @if($assemblyLog->notes)
        <div class="mt-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
            <h5 class="text-sm font-medium text-yellow-900 dark:text-yellow-200 mb-2">Note Assemblaggio</h5>
            <p class="text-sm text-yellow-800 dark:text-yellow-300 whitespace-pre-wrap">{{ $assemblyLog->notes }}</p>
        </div>
        @endif
    @endif
</div>
