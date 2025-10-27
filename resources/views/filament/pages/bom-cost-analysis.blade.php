<x-filament-panels::page>
    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>

    @if($showAnalysis)
        @php
            $metrics = $this->getOptimizationMetrics();
            $chartData = $this->getChartData();
        @endphp

        <!-- Optimization Metrics -->
        <x-filament::section class="mt-6">
            <x-slot name="heading">
                Optimization Opportunities
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg border border-green-200 dark:border-green-800">
                    <div class="text-sm text-green-600 dark:text-green-400">Potential Savings</div>
                    <div class="text-2xl font-bold text-green-700 dark:text-green-300">€{{ number_format($metrics['potential_savings'], 2) }}</div>
                </div>
                
                <div class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg border border-yellow-200 dark:border-yellow-800">
                    <div class="text-sm text-yellow-600 dark:text-yellow-400">Lifecycle Risks</div>
                    <div class="text-2xl font-bold text-yellow-700 dark:text-yellow-300">{{ $metrics['lifecycle_risks'] }}</div>
                </div>
                
                <div class="bg-red-50 dark:bg-red-900/20 p-4 rounded-lg border border-red-200 dark:border-red-800">
                    <div class="text-sm text-red-600 dark:text-red-400">Stock Shortages</div>
                    <div class="text-2xl font-bold text-red-700 dark:text-red-300">{{ $metrics['stock_shortages'] }}</div>
                </div>
                
                <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-800">
                    <div class="text-sm text-blue-600 dark:text-blue-400">Total Suggestions</div>
                    <div class="text-2xl font-bold text-blue-700 dark:text-blue-300">{{ $metrics['total_suggestions'] }}</div>
                </div>
            </div>
        </x-filament::section>

        <!-- Cost Trend Chart -->
        @if(count($chartData['labels']) > 0)
            <x-filament::section class="mt-6">
                <x-slot name="heading">
                    BOM Cost Trend
                </x-slot>

                <div class="h-64">
                    <canvas id="costTrendChart"></canvas>
                </div>

                @if(isset($costTrendData['summary']))
                    <div class="mt-4 grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500">Min Cost:</span>
                            <span class="font-medium">€{{ number_format($costTrendData['summary']['min_cost'], 2) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Max Cost:</span>
                            <span class="font-medium">€{{ number_format($costTrendData['summary']['max_cost'], 2) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Average:</span>
                            <span class="font-medium">€{{ number_format($costTrendData['summary']['avg_cost'], 2) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Variance:</span>
                            <span class="font-medium">€{{ number_format($costTrendData['summary']['cost_variance'], 2) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Trend:</span>
                            <span class="font-medium capitalize {{ $costTrendData['summary']['trend_direction'] === 'increasing' ? 'text-red-600' : ($costTrendData['summary']['trend_direction'] === 'decreasing' ? 'text-green-600' : 'text-gray-600') }}">
                                {{ $costTrendData['summary']['trend_direction'] }}
                            </span>
                        </div>
                    </div>
                @endif
            </x-filament::section>
        @endif

        <!-- Component Cost Breakdown -->
        @if(count($componentUsageData) > 0)
            <x-filament::section class="mt-6">
                <x-slot name="heading">
                    Component Cost Breakdown (Top 10)
                </x-slot>

                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Component</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Quantity</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Unit Cost</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total Cost</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Used in Projects</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">% of BOM Cost</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @php
                                $totalBomCost = collect($componentUsageData)->sum('total_cost');
                            @endphp
                            @foreach(array_slice($componentUsageData, 0, 10) as $usage)
                                <tr>
                                    <td class="px-4 py-2">
                                        <div>{{ $usage['component']->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $usage['component']->manufacturer }}</div>
                                    </td>
                                    <td class="px-4 py-2 text-center">{{ $usage['current_quantity'] }}</td>
                                    <td class="px-4 py-2 text-right">€{{ number_format($usage['unit_cost'], 2) }}</td>
                                    <td class="px-4 py-2 text-right font-medium">€{{ number_format($usage['total_cost'], 2) }}</td>
                                    <td class="px-4 py-2 text-center">{{ $usage['total_projects_using'] }}</td>
                                    <td class="px-4 py-2 text-right">
                                        @php
                                            $percentage = $totalBomCost > 0 ? ($usage['total_cost'] / $totalBomCost) * 100 : 0;
                                        @endphp
                                        {{ number_format($percentage, 1) }}%
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        <!-- Optimization Suggestions -->
        @if(count($optimizationSuggestions) > 0)
            <x-filament::section class="mt-6">
                <x-slot name="heading">
                    Optimization Suggestions
                </x-slot>

                <div class="space-y-4">
                    @foreach($optimizationSuggestions as $suggestion)
                        <div class="border rounded-lg p-4 {{ $suggestion['type'] === 'cost_optimization' ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20' : ($suggestion['type'] === 'lifecycle_risk' ? 'border-yellow-200 bg-yellow-50 dark:border-yellow-800 dark:bg-yellow-900/20' : 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20') }}">
                            <div class="flex items-start gap-3">
                                @if($suggestion['type'] === 'cost_optimization')
                                    <x-heroicon-o-currency-euro class="w-5 h-5 text-green-600 mt-0.5" />
                                @elseif($suggestion['type'] === 'lifecycle_risk')
                                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-yellow-600 mt-0.5" />
                                @else
                                    <x-heroicon-o-cube class="w-5 h-5 text-red-600 mt-0.5" />
                                @endif
                                
                                <div class="flex-1">
                                    <div class="font-medium">
                                        {{ ucfirst(str_replace('_', ' ', $suggestion['type'])) }}
                                        @if(isset($suggestion['designator']))
                                            - {{ $suggestion['designator'] }}
                                        @endif
                                    </div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        {{ $suggestion['notes'] }}
                                    </div>
                                    
                                    @if($suggestion['type'] === 'cost_optimization' && isset($suggestion['potential_savings']))
                                        <div class="mt-2 text-sm">
                                            <span class="text-green-600 font-medium">
                                                Potential savings: €{{ number_format($suggestion['potential_savings'], 2) }}
                                            </span>
                                            @if(isset($suggestion['compatibility_score']))
                                                <span class="text-gray-500 ml-2">
                                                    (Compatibility: {{ number_format($suggestion['compatibility_score'] * 100, 0) }}%)
                                                </span>
                                            @endif
                                        </div>
                                    @elseif($suggestion['type'] === 'stock_shortage')
                                        <div class="mt-2 text-sm text-red-600">
                                            Shortage: {{ $suggestion['shortage'] }} units
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const ctx = document.getElementById('costTrendChart');
                if (ctx) {
                    new Chart(ctx, {
                        type: 'line',
                        data: @json($chartData),
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: false,
                                    ticks: {
                                        callback: function(value) {
                                            return '€' + value.toFixed(0);
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            });
        </script>
    @endif
</x-filament-panels::page>