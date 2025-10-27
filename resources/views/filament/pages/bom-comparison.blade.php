<x-filament-panels::page>
    <x-filament::section>
        {{ $this->form }}

        <div class="mt-4">
            {{ $this->getActions()[0] ?? '' }}
            {{ $this->getActions()[1] ?? '' }}
        </div>
    </x-filament::section>

    @if($showComparison && $comparisonData)
        <!-- Cost Impact Summary -->
        <x-filament::section class="mt-6">
            <x-slot name="heading">
                Cost Impact Analysis
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="text-sm text-gray-600 dark:text-gray-400">BOM 1 Total</div>
                    <div class="text-2xl font-bold">€{{ number_format($comparisonData['cost_impact']['bom1_total'], 2) }}</div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="text-sm text-gray-600 dark:text-gray-400">BOM 2 Total</div>
                    <div class="text-2xl font-bold">€{{ number_format($comparisonData['cost_impact']['bom2_total'], 2) }}</div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Cost Difference</div>
                    <div class="text-2xl font-bold {{ $comparisonData['cost_impact']['difference'] < 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $comparisonData['cost_impact']['difference'] < 0 ? '-' : '+' }}€{{ number_format(abs($comparisonData['cost_impact']['difference']), 2) }}
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Percentage Change</div>
                    <div class="text-2xl font-bold {{ $comparisonData['cost_impact']['percentage_change'] < 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $comparisonData['cost_impact']['percentage_change'] < 0 ? '' : '+' }}{{ number_format($comparisonData['cost_impact']['percentage_change'], 1) }}%
                    </div>
                </div>
            </div>
        </x-filament::section>

        <!-- Changes Summary -->
        <x-filament::section class="mt-6">
            <x-slot name="heading">
                Changes Summary
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="text-center">
                    <div class="text-3xl font-bold text-green-600">{{ count($comparisonData['added']) }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Components Added</div>
                </div>
                
                <div class="text-center">
                    <div class="text-3xl font-bold text-red-600">{{ count($comparisonData['removed']) }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Components Removed</div>
                </div>
                
                <div class="text-center">
                    <div class="text-3xl font-bold text-yellow-600">{{ count($comparisonData['modified']) }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Components Modified</div>
                </div>
                
                <div class="text-center">
                    <div class="text-3xl font-bold text-gray-600">{{ count($comparisonData['unchanged']) }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Unchanged</div>
                </div>
            </div>
        </x-filament::section>

        <!-- Added Components -->
        @if(count($comparisonData['added']) > 0)
            <x-filament::section class="mt-6">
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-plus-circle class="w-5 h-5 text-green-600" />
                        Added Components ({{ count($comparisonData['added']) }})
                    </div>
                </x-slot>

                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Designator</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Component</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Quantity</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Cost Impact</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($comparisonData['added'] as $item)
                                <tr>
                                    <td class="px-4 py-2">{{ $item['designator'] }}</td>
                                    <td class="px-4 py-2">{{ $item['component']->name }}</td>
                                    <td class="px-4 py-2">{{ $item['quantity'] }}</td>
                                    <td class="px-4 py-2 text-right text-green-600">+€{{ number_format($item['cost_impact'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        <!-- Removed Components -->
        @if(count($comparisonData['removed']) > 0)
            <x-filament::section class="mt-6">
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-minus-circle class="w-5 h-5 text-red-600" />
                        Removed Components ({{ count($comparisonData['removed']) }})
                    </div>
                </x-slot>

                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Designator</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Component</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Quantity</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Cost Impact</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($comparisonData['removed'] as $item)
                                <tr>
                                    <td class="px-4 py-2">{{ $item['designator'] }}</td>
                                    <td class="px-4 py-2">{{ $item['component']->name }}</td>
                                    <td class="px-4 py-2">{{ $item['quantity'] }}</td>
                                    <td class="px-4 py-2 text-right text-red-600">€{{ number_format($item['cost_impact'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        <!-- Modified Components -->
        @if(count($comparisonData['modified']) > 0)
            <x-filament::section class="mt-6">
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-arrows-right-left class="w-5 h-5 text-yellow-600" />
                        Modified Components ({{ count($comparisonData['modified']) }})
                    </div>
                </x-slot>

                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Designator</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Old Component</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">New Component</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Qty Change</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Cost Impact</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($comparisonData['modified'] as $item)
                                <tr>
                                    <td class="px-4 py-2">{{ $item['designator'] }}</td>
                                    <td class="px-4 py-2">
                                        <div>{{ $item['old']['component']->name }}</div>
                                        <div class="text-xs text-gray-500">Qty: {{ $item['old']['quantity'] }}</div>
                                    </td>
                                    <td class="px-4 py-2">
                                        <div>{{ $item['new']['component']->name }}</div>
                                        <div class="text-xs text-gray-500">Qty: {{ $item['new']['quantity'] }}</div>
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        @if($item['old']['quantity'] != $item['new']['quantity'])
                                            <span class="text-sm {{ $item['new']['quantity'] > $item['old']['quantity'] ? 'text-green-600' : 'text-red-600' }}">
                                                {{ $item['new']['quantity'] - $item['old']['quantity'] > 0 ? '+' : '' }}{{ $item['new']['quantity'] - $item['old']['quantity'] }}
                                            </span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right {{ $item['cost_impact'] < 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $item['cost_impact'] < 0 ? '-' : '+' }}€{{ number_format(abs($item['cost_impact']), 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>