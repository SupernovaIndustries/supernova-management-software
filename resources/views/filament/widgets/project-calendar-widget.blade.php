<div class="filament-widget col-span-full">
    <x-filament-widgets::widget class="col-span-full">
        <x-filament::section>
            <x-slot name="heading">
                Project Calendar
            </x-slot>

            <x-slot name="headerEnd">
                <div class="flex gap-2">
                    <x-filament::button
                        wire:click="previousMonth"
                        size="sm"
                        color="gray"
                        icon="heroicon-o-chevron-left"
                    />
                    <span class="px-3 py-1 text-sm font-medium">
                        {{ $this->getCurrentMonthName() }}
                    </span>
                    <x-filament::button
                        wire:click="nextMonth"
                        size="sm"
                        color="gray"
                        icon="heroicon-o-chevron-right"
                    />
                </div>
            </x-slot>

            <div class="calendar-container">
                <!-- Days of Week Header -->
                <div class="grid grid-cols-7 gap-0 border-t border-l border-gray-200">
                    @foreach(['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $dayName)
                        <div class="border-r border-b border-gray-200 p-2 text-center text-sm font-medium text-gray-700 bg-gray-50">
                            {{ $dayName }}
                        </div>
                    @endforeach
                </div>

                <!-- Calendar Grid -->
                <div class="grid grid-cols-7 gap-0 border-l border-gray-200">
                    @foreach($this->getCalendarDays() as $week)
                        @foreach($week as $day)
                            <div class="{{ $this->getDayClasses($day) }}">
                                <!-- Day Number -->
                                <div class="flex justify-between items-start mb-1">
                                    <span class="text-sm font-medium {{ $day['is_today'] ? 'text-blue-600' : '' }}">
                                        {{ $day['day'] }}
                                    </span>
                                    @if($day['is_today'])
                                        <div class="w-2 h-2 bg-blue-600 rounded-full"></div>
                                    @endif
                                </div>

                                <!-- Events -->
                                <div class="space-y-1">
                                    @foreach($day['events'] as $event)
                                        <div class="text-xs p-1 rounded {{ $event['color'] }} truncate"
                                             title="{{ $event['title'] }}">
                                            {{ $event['title'] }}
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @endforeach
                </div>

                <!-- Legend -->
                <div class="mt-4 flex flex-wrap gap-4 text-xs">
                    <div class="flex items-center gap-1">
                        <div class="w-3 h-3 bg-green-100 border border-green-200 rounded"></div>
                        <span class="text-green-800">Project Start</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <div class="w-3 h-3 bg-blue-100 border border-blue-200 rounded"></div>
                        <span class="text-blue-800">Project Due</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <div class="w-3 h-3 bg-red-100 border border-red-200 rounded"></div>
                        <span class="text-red-800">Overdue</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <div class="w-3 h-3 bg-purple-100 border border-purple-200 rounded"></div>
                        <span class="text-purple-800">Activities</span>
                    </div>
                </div>
            </div>
        </x-filament::section>
    </x-filament-widgets::widget>
</div>

<style>
.calendar-container {
    font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
}
</style>