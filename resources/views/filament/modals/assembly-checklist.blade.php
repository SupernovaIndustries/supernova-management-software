<div class="p-4">
    {{-- Header with Assembly Info --}}
    <div class="mb-6 p-4 bg-gray-100 dark:bg-gray-800 rounded-lg">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <span class="font-semibold text-gray-700 dark:text-gray-300">Progetto:</span>
                <p class="text-gray-900 dark:text-white">{{ $assemblyLog->project->code }}</p>
            </div>
            <div>
                <span class="font-semibold text-gray-700 dark:text-gray-300">Batch:</span>
                <p class="text-gray-900 dark:text-white">{{ $assemblyLog->batch_number }}</p>
            </div>
            <div>
                <span class="font-semibold text-gray-700 dark:text-gray-300">Schede:</span>
                <p class="text-gray-900 dark:text-white">{{ $assemblyLog->boards_count }}</p>
            </div>
            <div>
                <span class="font-semibold text-gray-700 dark:text-gray-300">Tipo:</span>
                <p class="text-gray-900 dark:text-white">{{ $assemblyLog->is_prototype ? 'Test/Prototipo' : 'Produzione' }}</p>
            </div>
        </div>
    </div>

    @if($checklist)
        {{-- Checklist Status Overview --}}
        <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            {{-- Progress Card --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Progresso</span>
                    <span class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                        {{ number_format($checklist->completion_percentage, 0) }}%
                    </span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                    <div class="bg-blue-600 dark:bg-blue-500 h-2.5 rounded-full transition-all duration-300"
                         style="width: {{ $checklist->completion_percentage }}%"></div>
                </div>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    {{ $checklist->completed_items }}/{{ $checklist->total_items }} items completati
                </p>
            </div>

            {{-- Status Card --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400 block mb-2">Stato</span>
                @php
                    $statusColors = [
                        'not_started' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                        'in_progress' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                        'completed' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                        'failed' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                        'on_hold' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                    ];
                    $statusLabels = [
                        'not_started' => 'Non Iniziata',
                        'in_progress' => 'In Corso',
                        'completed' => 'Completata',
                        'failed' => 'Fallita',
                        'on_hold' => 'In Attesa',
                    ];
                @endphp
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $statusColors[$checklist->status] ?? $statusColors['not_started'] }}">
                    {{ $statusLabels[$checklist->status] ?? $checklist->status }}
                </span>

                @if($checklist->started_at)
                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        Iniziata: {{ $checklist->started_at->format('d/m/Y H:i') }}
                    </p>
                @endif
                @if($checklist->completed_at)
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Completata: {{ $checklist->completed_at->format('d/m/Y H:i') }}
                    </p>
                @endif
            </div>

            {{-- Assigned Card --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400 block mb-2">Assegnazione</span>
                @if($checklist->assignedUser)
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ $checklist->assignedUser->name }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Operatore</p>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">Non assegnata</p>
                @endif

                @if($checklist->supervisor)
                    <div class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ $checklist->supervisor->name }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Supervisore</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Checklist Items by Category --}}
        @php
            $categories = $checklist->template->getItemsByCategory();
        @endphp

        @foreach($categories as $categoryName => $categoryItems)
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                    <span class="inline-block w-1 h-6 bg-blue-600 mr-2 rounded"></span>
                    {{ $categoryName }}
                    <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">
                        ({{ $categoryItems->count() }} items)
                    </span>
                </h3>

                <div class="space-y-3">
                    @foreach($categoryItems as $item)
                        @php
                            $response = $checklist->responses->where('item_id', $item->id)->first();
                            $isCompleted = $response && $response->status === 'completed';
                            $isFailed = $response && $response->status === 'failed';
                            $isPending = $response && $response->status === 'pending';
                        @endphp

                        <div class="bg-white dark:bg-gray-800 rounded-lg border p-4 {{ $isCompleted ? 'border-green-300 dark:border-green-700' : ($isFailed ? 'border-red-300 dark:border-red-700' : 'border-gray-200 dark:border-gray-700') }}">
                            <div class="flex items-start">
                                {{-- Checkbox/Status Icon --}}
                                <div class="flex-shrink-0 mr-3 mt-0.5">
                                    @if($isCompleted)
                                        <svg class="w-6 h-6 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                    @elseif($isFailed)
                                        <svg class="w-6 h-6 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                        </svg>
                                    @else
                                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <circle cx="12" cy="12" r="10" stroke-width="2"/>
                                        </svg>
                                    @endif
                                </div>

                                {{-- Item Content --}}
                                <div class="flex-1">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <h4 class="text-sm font-medium text-gray-900 dark:text-white flex items-center">
                                                {{ $item->title }}
                                                @if($item->is_critical)
                                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                        CRITICAL
                                                    </span>
                                                @endif
                                                @if($item->is_required)
                                                    <span class="ml-1 text-red-500">*</span>
                                                @endif
                                            </h4>

                                            @if($item->description)
                                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                    {{ $item->description }}
                                                </p>
                                            @endif

                                            @if($item->instructions)
                                                <div class="mt-2 p-2 bg-blue-50 dark:bg-blue-900/20 rounded text-xs text-gray-700 dark:text-gray-300">
                                                    <strong>Istruzioni:</strong> {{ $item->instructions }}
                                                </div>
                                            @endif

                                            @if($item->safety_notes)
                                                <div class="mt-2 p-2 bg-yellow-50 dark:bg-yellow-900/20 rounded text-xs text-yellow-800 dark:text-yellow-200 flex items-start">
                                                    <svg class="w-4 h-4 mr-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                    </svg>
                                                    <span><strong>Safety:</strong> {{ $item->safety_notes }}</span>
                                                </div>
                                            @endif

                                            {{-- Response Data/Notes --}}
                                            @if($response && $response->response_data)
                                                <div class="mt-2 p-2 bg-gray-50 dark:bg-gray-700 rounded text-sm">
                                                    <strong class="text-gray-700 dark:text-gray-300">Risposta:</strong>
                                                    <span class="text-gray-900 dark:text-white">{{ is_array($response->response_data) ? json_encode($response->response_data) : $response->response_data }}</span>
                                                </div>
                                            @endif

                                            @if($response && $response->notes)
                                                <div class="mt-2 p-2 bg-gray-50 dark:bg-gray-700 rounded text-sm">
                                                    <strong class="text-gray-700 dark:text-gray-300">Note:</strong>
                                                    <span class="text-gray-900 dark:text-white">{{ $response->notes }}</span>
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Metadata --}}
                                        <div class="ml-4 flex-shrink-0 text-right">
                                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                                {{ $item->getTypeIcon() }} {{ ucfirst($item->type) }}
                                            </span>
                                            @if($item->estimated_minutes)
                                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    ~{{ $item->estimated_minutes }} min
                                                </p>
                                            @endif
                                            @if($response && $response->completed_at)
                                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $response->completed_at->format('d/m H:i') }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        {{-- Notes Section --}}
        @if($checklist->notes)
            <div class="mt-6 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                <h4 class="text-sm font-semibold text-yellow-900 dark:text-yellow-200 mb-2">Note Generali</h4>
                <p class="text-sm text-yellow-800 dark:text-yellow-300">{{ $checklist->notes }}</p>
            </div>
        @endif

        @if($checklist->failure_reason)
            <div class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                <h4 class="text-sm font-semibold text-red-900 dark:text-red-200 mb-2">Motivo Fallimento</h4>
                <p class="text-sm text-red-800 dark:text-red-300">{{ $checklist->failure_reason }}</p>
            </div>
        @endif

        {{-- Footer Info --}}
        <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-500 dark:text-gray-400 flex justify-between">
            <div>
                Template: <strong>{{ $checklist->template->name }}</strong>
                @if($checklist->template->metadata && isset($checklist->template->metadata['generated_by']))
                    <span class="ml-2 px-2 py-0.5 bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 rounded">
                        AI Generated
                    </span>
                @endif
            </div>
            <div>
                Creata: {{ $checklist->created_at->format('d/m/Y H:i') }}
            </div>
        </div>

    @else
        {{-- Empty State --}}
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Nessuna Checklist</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">La checklist verrà generata automaticamente o può essere creata manualmente.</p>
        </div>
    @endif
</div>

{{-- Print Styles --}}
<style>
    @media print {
        .fi-modal-header,
        .fi-modal-footer {
            display: none !important;
        }

        body {
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }
    }
</style>
