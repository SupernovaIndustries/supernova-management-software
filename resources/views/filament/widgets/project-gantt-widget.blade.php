<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Project Gantt Chart
        </x-slot>

        <div class="gantt-container overflow-x-auto">
            @php
                $projects = $this->getProjects();
                $earliestDate = $this->getEarliestDate();
                $latestDate = $this->getLatestDate();
                $totalDays = $this->getTotalTimespan();
            @endphp

            @if($projects->count() > 0)
                <!-- Timeline Header -->
                <div class="gantt-timeline mb-4">
                    <div class="flex text-xs text-gray-600 border-b pb-2">
                        <div class="w-64 font-medium">Project</div>
                        <div class="flex-1 min-w-max">
                            <div class="flex">
                                @for($i = 0; $i <= $totalDays; $i += 7)
                                    @php
                                        $currentDate = $earliestDate->copy()->addDays($i);
                                    @endphp
                                    <div class="w-14 text-center border-l border-gray-200">
                                        {{ $currentDate->format('M d') }}
                                    </div>
                                @endfor
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gantt Chart Body -->
                <div class="gantt-body space-y-2">
                    @foreach($projects as $project)
                        <div class="gantt-row flex items-center">
                            <!-- Project Info -->
                            <div class="w-64 pr-4">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ $project['name'] }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ $project['customer'] }} • {{ $project['progress'] }}%
                                </div>
                                <div class="text-xs">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $project['status_color'] }} text-white">
                                        {{ ucfirst(str_replace('_', ' ', $project['status'])) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Timeline Bar -->
                            <div class="flex-1 min-w-max relative h-8">
                                @php
                                    $startOffset = $project['start_date']->diffInDays($earliestDate);
                                    $barWidth = $project['total_days'];
                                    $progressWidth = $project['progress_days'];
                                    $leftPosition = ($startOffset / $totalDays) * 100;
                                    $fullBarWidth = ($barWidth / $totalDays) * 100;
                                    $progressBarWidth = ($progressWidth / $totalDays) * 100;
                                @endphp

                                <!-- Background Timeline -->
                                <div class="absolute inset-0 flex">
                                    @for($i = 0; $i <= $totalDays; $i += 7)
                                        <div class="w-14 border-l border-gray-100 h-full"></div>
                                    @endfor
                                </div>

                                <!-- Project Bar Background -->
                                <div class="absolute top-2 h-4 bg-gray-200 rounded" 
                                     style="left: {{ $leftPosition }}%; width: {{ $fullBarWidth }}%;">
                                </div>

                                <!-- Progress Bar -->
                                <div class="absolute top-2 h-4 {{ $project['status_color'] }} rounded opacity-80" 
                                     style="left: {{ $leftPosition }}%; width: {{ $progressBarWidth }}%;">
                                </div>

                                <!-- Today Indicator -->
                                @php
                                    $todayOffset = now()->diffInDays($earliestDate);
                                    $todayPosition = ($todayOffset / $totalDays) * 100;
                                @endphp
                                @if($todayOffset >= 0 && $todayOffset <= $totalDays)
                                    <div class="absolute top-0 bottom-0 w-0.5 bg-red-500 z-10" 
                                         style="left: {{ $todayPosition }}%;">
                                    </div>
                                @endif

                                <!-- Overdue Indicator -->
                                @if($project['is_overdue'])
                                    <div class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full border-2 border-white"></div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Legend -->
                <div class="mt-6 flex flex-wrap gap-4 text-xs text-gray-600">
                    <div class="flex items-center gap-1">
                        <div class="w-3 h-3 bg-gray-200 rounded"></div>
                        <span>Total Duration</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <div class="w-3 h-3 bg-blue-500 rounded"></div>
                        <span>Progress</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <div class="w-0.5 h-3 bg-red-500"></div>
                        <span>Today</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                        <span>Overdue</span>
                    </div>
                </div>
            @else
                <div class="text-center py-8 text-gray-500">
                    <p>No projects with start and due dates found.</p>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

<style>
.gantt-container {
    font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
}

.gantt-row:hover {
    background-color: #f9fafb;
}
</style>