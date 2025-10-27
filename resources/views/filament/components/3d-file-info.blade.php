<div class="space-y-4">
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="text-lg font-semibold mb-2">{{ $record->name }}</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $format_info }}</p>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">File Originale:</p>
            <p class="text-sm">{{ $record->original_filename }}</p>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Dimensione:</p>
            <p class="text-sm">{{ $record->formatted_file_size }}</p>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Formato:</p>
            <p class="text-sm uppercase">{{ $extension }}</p>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Caricato:</p>
            <p class="text-sm">{{ $record->created_at->format('d/m/Y H:i') }}</p>
        </div>
    </div>

    @if($record->description)
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Descrizione:</p>
            <p class="text-sm">{{ $record->description }}</p>
        </div>
    @endif

    <div class="border-t pt-4">
        <p class="text-xs text-gray-500 dark:text-gray-500">
            @if(in_array($extension, ['stl', '3mf', 'amf']))
                💡 Questo file può essere utilizzato direttamente per la stampa 3D
            @elseif(in_array($extension, ['step', 'stp', 'iges', 'igs', 'f3d']))
                💡 Questo è un file CAD parametrico che può essere modificato
            @elseif(in_array($extension, ['dxf', 'dwg']))
                💡 Questo file contiene disegni tecnici 2D/3D
            @endif
        </p>
    </div>

    @if($record->tags && count($record->tags) > 0)
        <div class="flex flex-wrap gap-2">
            @foreach($record->tags as $tag)
                <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-700 bg-blue-100 rounded-full dark:bg-blue-900 dark:text-blue-300">
                    {{ $tag }}
                </span>
            @endforeach
        </div>
    @endif
</div>