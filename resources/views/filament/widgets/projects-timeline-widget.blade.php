<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            📅 Timeline Progetti Intelligente
        </x-slot>

        <x-slot name="description">
            Visualizzazione timeline con priorità AI e milestone in scadenza
        </x-slot>

        <div class="space-y-6">
            {{-- Upcoming Deadlines Alert --}}
            @php
                $upcomingDeadlines = $this->getUpcomingDeadlines();
            @endphp

            @if($upcomingDeadlines->count() > 0)
            <div class="rounded-lg bg-warning-50 dark:bg-warning-400/10 p-4 border border-warning-600/20">
                <div class="flex items-start gap-3">
                    <div class="text-warning-600 dark:text-warning-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-sm font-semibold text-warning-800 dark:text-warning-200 mb-2">
                            ⚠️ Scadenze Imminenti (prossimi 7 giorni)
                        </h4>
                        <ul class="space-y-1 text-sm text-warning-700 dark:text-warning-300">
                            @foreach($upcomingDeadlines as $deadline)
                            <li>
                                <span class="font-medium">{{ $deadline['code'] }}</span>:
                                {{ $deadline['due_date'] }}
                                ({{ $deadline['days_left'] }} giorni - {{ number_format($deadline['completion'], 0) }}% completato)
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
            @endif

            {{-- Projects Timeline --}}
            <div class="space-y-3">
                @foreach($this->getProjects() as $project)
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden hover:border-primary-500 dark:hover:border-primary-400 transition">
                    {{-- Project Header --}}
                    <div class="bg-gray-50 dark:bg-gray-800 px-4 py-3 flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-mono font-semibold text-gray-900 dark:text-white">
                                    {{ $project['code'] }}
                                </span>

                                {{-- Priority Badge --}}
                                @if($project['ai_priority_score'])
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    @if($project['ai_priority_score'] >= 90) bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-200
                                    @elseif($project['ai_priority_score'] >= 70) bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-200
                                    @elseif($project['ai_priority_score'] >= 50) bg-info-100 text-info-800 dark:bg-info-900 dark:text-info-200
                                    @else bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200
                                    @endif">
                                    🎯 {{ $project['ai_priority_score'] }}
                                </span>
                                @endif

                                {{-- Status Badge --}}
                                <x-filament::badge :color="$project['status_color']">
                                    {{ ucfirst($project['status']) }}
                                </x-filament::badge>

                                {{-- Overdue Badge --}}
                                @if($project['is_overdue'])
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-200">
                                    🚨 IN RITARDO
                                </span>
                                @endif
                            </div>

                            <div class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                {{ $project['name'] }} • {{ $project['customer'] }}
                            </div>
                        </div>

                        <div class="text-right text-sm">
                            <div class="font-medium text-gray-900 dark:text-white">
                                {{ number_format($project['completion_percentage'], 1) }}%
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                @if($project['days_until_deadline'] !== null)
                                    @if($project['days_until_deadline'] < 0)
                                        {{ abs($project['days_until_deadline']) }} giorni in ritardo
                                    @else
                                        {{ $project['days_until_deadline'] }} giorni rimanenti
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Timeline Bar --}}
                    <div class="px-4 py-3 bg-white dark:bg-gray-900">
                        <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 mb-2">
                            <span>{{ $project['start_date']->format('d/m/Y') }}</span>
                            <span class="flex-1 border-t border-gray-200 dark:border-gray-700"></span>
                            <span>{{ $project['due_date']->format('d/m/Y') }}</span>
                        </div>

                        {{-- Progress Bar --}}
                        <div class="relative w-full h-8 bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden">
                            <div class="absolute inset-0 flex items-center">
                                {{-- Completion Progress --}}
                                <div
                                    class="h-full bg-gradient-to-r from-primary-500 to-primary-600 transition-all duration-300"
                                    style="width: {{ $project['completion_percentage'] }}%">
                                </div>
                            </div>

                            {{-- Current Time Marker --}}
                            @php
                                $timeProgressPercentage = min(100, max(0, ($project['days_from_start'] / $project['total_days']) * 100));
                            @endphp
                            <div
                                class="absolute top-0 bottom-0 w-0.5 bg-gray-900 dark:bg-white z-10"
                                style="left: {{ $timeProgressPercentage }}%"
                                title="Oggi">
                            </div>
                        </div>

                        {{-- Upcoming Milestones --}}
                        @if($project['upcoming_milestones']->count() > 0)
                        <div class="mt-3 space-y-1">
                            <div class="text-xs font-medium text-gray-700 dark:text-gray-300">
                                📌 Milestone in scadenza:
                            </div>
                            @foreach($project['upcoming_milestones'] as $milestone)
                            <div class="text-xs text-gray-600 dark:text-gray-400 pl-4">
                                • {{ $milestone['name'] }} ({{ $milestone['target_date'] }})
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach

                @if($this->getProjects()->count() === 0)
                <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                    <svg class="w-12 h-12 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <p>Nessun progetto attivo con date definite</p>
                </div>
                @endif
            </div>

            {{-- Overlaps Summary --}}
            @php
                $overlaps = $this->getOverlappingProjects();
            @endphp

            @if(count($overlaps) > 0)
            <div class="rounded-lg bg-info-50 dark:bg-info-400/10 p-4 border border-info-600/20">
                <h4 class="text-sm font-semibold text-info-800 dark:text-info-200 mb-2">
                    ℹ️ Sovrapposizioni Temporali Rilevate ({{ count($overlaps) }})
                </h4>
                <div class="text-xs text-info-700 dark:text-info-300">
                    Ci sono {{ count($overlaps) }} sovrapposizioni temporali tra i progetti.
                    Usa <code class="bg-info-100 dark:bg-info-900 px-1 py-0.5 rounded">php artisan projects:calculate-priorities</code>
                    per ottimizzazioni AI.
                </div>
            </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
