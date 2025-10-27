<div class="flex flex-col items-center justify-center py-12 px-4">
    <div class="flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
        @if(isset($icon))
            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                @if($icon === 'heroicon-o-document')
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                @else
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                @endif
            </svg>
        @endif
    </div>

    <h3 class="text-lg font-semibold text-gray-900 mb-2">
        {{ $heading ?? 'Nessun elemento' }}
    </h3>

    @if(isset($description))
    <p class="text-sm text-gray-600 text-center max-w-sm">
        {{ $description }}
    </p>
    @endif
</div>
