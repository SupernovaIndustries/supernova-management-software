<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Component Risk Matrix
        </x-slot>

        <x-slot name="description">
            Components plotted by Lifecycle Risk vs Certification Risk
        </x-slot>

        <div class="risk-matrix-container">
            @php
                $matrix = $this->getRiskMatrix();
                $riskCounts = $this->getRiskCounts();
            @endphp

            <!-- Summary Stats -->
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="text-center p-4 bg-green-50 border border-green-200 rounded-lg">
                    <div class="text-2xl font-bold text-green-600">{{ $riskCounts['low'] }}</div>
                    <div class="text-sm text-green-700">Low Risk</div>
                </div>
                <div class="text-center p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div class="text-2xl font-bold text-yellow-600">{{ $riskCounts['medium'] }}</div>
                    <div class="text-sm text-yellow-700">Medium Risk</div>
                </div>
                <div class="text-center p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="text-2xl font-bold text-red-600">{{ $riskCounts['high'] }}</div>
                    <div class="text-sm text-red-700">High Risk</div>
                </div>
            </div>

            <!-- Risk Matrix Grid -->
            <div class="risk-matrix-grid">
                <!-- Y-axis label -->
                <div class="flex items-center justify-center text-sm font-medium text-gray-700 transform -rotate-90 w-8">
                    <span>Lifecycle Risk</span>
                </div>

                <!-- Grid container -->
                <div class="flex-1">
                    <!-- X-axis labels -->
                    <div class="grid grid-cols-3 gap-1 mb-1">
                        <div class="text-center text-sm font-medium text-gray-700">Low</div>
                        <div class="text-center text-sm font-medium text-gray-700">Medium</div>
                        <div class="text-center text-sm font-medium text-gray-700">High</div>
                    </div>
                    <div class="text-center text-xs text-gray-500 mb-2">Certification Risk</div>

                    <!-- Matrix grid -->
                    <div class="grid grid-cols-3 gap-1">
                        <!-- High Lifecycle Risk Row -->
                        <div class="h-24 p-2 border border-yellow-300 bg-yellow-50 rounded text-xs overflow-hidden">
                            <div class="font-medium text-yellow-800 mb-1">{{ $matrix['high_low']->count() }} components</div>
                            @foreach($matrix['high_low']->take(3) as $component)
                                <div class="truncate text-yellow-700">{{ $component['name'] }}</div>
                            @endforeach
                            @if($matrix['high_low']->count() > 3)
                                <div class="text-yellow-600">+{{ $matrix['high_low']->count() - 3 }} more</div>
                            @endif
                        </div>

                        <div class="h-24 p-2 border border-red-300 bg-red-50 rounded text-xs overflow-hidden">
                            <div class="font-medium text-red-800 mb-1">{{ $matrix['high_medium']->count() }} components</div>
                            @foreach($matrix['high_medium']->take(3) as $component)
                                <div class="truncate text-red-700">{{ $component['name'] }}</div>
                            @endforeach
                            @if($matrix['high_medium']->count() > 3)
                                <div class="text-red-600">+{{ $matrix['high_medium']->count() - 3 }} more</div>
                            @endif
                        </div>

                        <div class="h-24 p-2 border border-red-400 bg-red-100 rounded text-xs overflow-hidden">
                            <div class="font-medium text-red-900 mb-1">{{ $matrix['high_high']->count() }} components</div>
                            @foreach($matrix['high_high']->take(3) as $component)
                                <div class="truncate text-red-800">{{ $component['name'] }}</div>
                            @endforeach
                            @if($matrix['high_high']->count() > 3)
                                <div class="text-red-700">+{{ $matrix['high_high']->count() - 3 }} more</div>
                            @endif
                        </div>

                        <!-- Medium Lifecycle Risk Row -->
                        <div class="h-24 p-2 border border-green-300 bg-green-50 rounded text-xs overflow-hidden">
                            <div class="font-medium text-green-800 mb-1">{{ $matrix['medium_low']->count() }} components</div>
                            @foreach($matrix['medium_low']->take(3) as $component)
                                <div class="truncate text-green-700">{{ $component['name'] }}</div>
                            @endforeach
                            @if($matrix['medium_low']->count() > 3)
                                <div class="text-green-600">+{{ $matrix['medium_low']->count() - 3 }} more</div>
                            @endif
                        </div>

                        <div class="h-24 p-2 border border-yellow-300 bg-yellow-50 rounded text-xs overflow-hidden">
                            <div class="font-medium text-yellow-800 mb-1">{{ $matrix['medium_medium']->count() }} components</div>
                            @foreach($matrix['medium_medium']->take(3) as $component)
                                <div class="truncate text-yellow-700">{{ $component['name'] }}</div>
                            @endforeach
                            @if($matrix['medium_medium']->count() > 3)
                                <div class="text-yellow-600">+{{ $matrix['medium_medium']->count() - 3 }} more</div>
                            @endif
                        </div>

                        <div class="h-24 p-2 border border-red-300 bg-red-50 rounded text-xs overflow-hidden">
                            <div class="font-medium text-red-800 mb-1">{{ $matrix['medium_high']->count() }} components</div>
                            @foreach($matrix['medium_high']->take(3) as $component)
                                <div class="truncate text-red-700">{{ $component['name'] }}</div>
                            @endforeach
                            @if($matrix['medium_high']->count() > 3)
                                <div class="text-red-600">+{{ $matrix['medium_high']->count() - 3 }} more</div>
                            @endif
                        </div>

                        <!-- Low Lifecycle Risk Row -->
                        <div class="h-24 p-2 border border-green-400 bg-green-100 rounded text-xs overflow-hidden">
                            <div class="font-medium text-green-900 mb-1">{{ $matrix['low_low']->count() }} components</div>
                            @foreach($matrix['low_low']->take(3) as $component)
                                <div class="truncate text-green-800">{{ $component['name'] }}</div>
                            @endforeach
                            @if($matrix['low_low']->count() > 3)
                                <div class="text-green-700">+{{ $matrix['low_low']->count() - 3 }} more</div>
                            @endif
                        </div>

                        <div class="h-24 p-2 border border-green-300 bg-green-50 rounded text-xs overflow-hidden">
                            <div class="font-medium text-green-800 mb-1">{{ $matrix['low_medium']->count() }} components</div>
                            @foreach($matrix['low_medium']->take(3) as $component)
                                <div class="truncate text-green-700">{{ $component['name'] }}</div>
                            @endforeach
                            @if($matrix['low_medium']->count() > 3)
                                <div class="text-green-600">+{{ $matrix['low_medium']->count() - 3 }} more</div>
                            @endif
                        </div>

                        <div class="h-24 p-2 border border-yellow-300 bg-yellow-50 rounded text-xs overflow-hidden">
                            <div class="font-medium text-yellow-800 mb-1">{{ $matrix['low_high']->count() }} components</div>
                            @foreach($matrix['low_high']->take(3) as $component)
                                <div class="truncate text-yellow-700">{{ $component['name'] }}</div>
                            @endforeach
                            @if($matrix['low_high']->count() > 3)
                                <div class="text-yellow-600">+{{ $matrix['low_high']->count() - 3 }} more</div>
                            @endif
                        </div>
                    </div>

                    <!-- Y-axis labels -->
                    <div class="flex flex-col justify-between absolute right-0 top-8 bottom-2 w-12">
                        <div class="text-xs text-gray-700 text-center">High</div>
                        <div class="text-xs text-gray-700 text-center">Med</div>
                        <div class="text-xs text-gray-700 text-center">Low</div>
                    </div>
                </div>
            </div>

            <!-- Legend -->
            <div class="mt-4 text-xs text-gray-600">
                <div class="flex flex-wrap gap-4">
                    <div class="flex items-center gap-1">
                        <div class="w-3 h-3 bg-green-100 border border-green-400 rounded"></div>
                        <span>Low Risk</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <div class="w-3 h-3 bg-yellow-50 border border-yellow-300 rounded"></div>
                        <span>Medium Risk</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <div class="w-3 h-3 bg-red-50 border border-red-300 rounded"></div>
                        <span>High Risk</span>
                    </div>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

<style>
.risk-matrix-grid {
    display: flex;
    position: relative;
}

.risk-matrix-container {
    font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
}
</style>