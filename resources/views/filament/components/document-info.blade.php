<div class="space-y-4">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Nome File Originale</p>
            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $record->original_filename }}</p>
        </div>

        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Tipo MIME</p>
            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $record->mime_type ?? 'N/A' }}</p>
        </div>

        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Dimensione File</p>
            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $record->formatted_file_size }}</p>
        </div>

        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Data Documento</p>
            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                {{ $record->document_date ? $record->document_date->format('d/m/Y') : 'N/A' }}
            </p>
        </div>

        @if($record->amount)
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Importo</p>
            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                {{ number_format($record->amount, 2, ',', '.') }} {{ $record->currency }}
            </p>
        </div>
        @endif

        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Data Caricamento</p>
            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $record->created_at->format('d/m/Y H:i') }}</p>
        </div>
    </div>

    @if($record->description)
    <div>
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Descrizione</p>
        <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $record->description }}</p>
    </div>
    @endif

    @if($record->tags && count($record->tags) > 0)
    <div>
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Tag</p>
        <div class="mt-1 flex flex-wrap gap-2">
            @foreach($record->tags as $tag)
                <span class="inline-flex items-center rounded-full bg-primary-50 dark:bg-primary-900/20 px-2 py-1 text-xs font-medium text-primary-700 dark:text-primary-400">
                    {{ $tag }}
                </span>
            @endforeach
        </div>
    </div>
    @endif

    @if($record->is3DFile() || $record->isCadFile())
    <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10 3.5a1.5 1.5 0 013 0V4a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-.5a1.5 1.5 0 000 3h.5a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-.5a1.5 1.5 0 00-3 0v.5a1 1 0 01-1 1H6a1 1 0 01-1-1v-3a1 1 0 00-1-1h-.5a1.5 1.5 0 010-3H4a1 1 0 001-1V6a1 1 0 011-1h3a1 1 0 001-1v-.5z" />
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-800 dark:text-blue-300">File 3D/CAD Rilevato</h3>
                <div class="mt-2 text-sm text-blue-700 dark:text-blue-400">
                    <p>
                        Questo file è un modello 3D o un disegno CAD.
                        @if($record->is3DFile())
                            Può essere utilizzato per stampa 3D o visualizzazione.
                        @else
                            Può essere aperto con software CAD compatibili.
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="rounded-lg bg-gray-50 dark:bg-gray-800/50 p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400">
            <strong>Percorso Storage:</strong> {{ $record->file_path }}
        </p>
    </div>
</div>
