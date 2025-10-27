<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        @if($this->comparisonData && count($this->comparisonData) > 0)
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- File 1 Details -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">
                        Version {{ $comparisonData['file1']['version'] }}
                        @if($comparisonData['differences']['version']['comparison'] > 0)
                            <span class="text-sm text-gray-500 dark:text-gray-400">(Older)</span>
                        @endif
                    </h3>
                    
                    <dl class="space-y-2">
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-600 dark:text-gray-400">Filename:</dt>
                            <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $comparisonData['file1']['filename'] }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-600 dark:text-gray-400">Format:</dt>
                            <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ strtoupper($comparisonData['file1']['format']) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-600 dark:text-gray-400">File Size:</dt>
                            <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ number_format($comparisonData['file1']['file_size'] / 1024 / 1024, 2) }} MB</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-600 dark:text-gray-400">Uploaded:</dt>
                            <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $comparisonData['file1']['created_at']->format('Y-m-d H:i') }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-600 dark:text-gray-400">Uploaded By:</dt>
                            <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $comparisonData['file1']['uploaded_by'] }}</dd>
                        </div>
                        
                        @if($comparisonData['file1']['drc_results'])
                            <div class="pt-4 border-t">
                                <dt class="text-sm text-gray-600 dark:text-gray-400 mb-2">DRC Results:</dt>
                                <dd class="flex space-x-4">
                                    <span class="text-red-600 dark:text-red-400">
                                        <span class="font-bold">{{ $comparisonData['file1']['drc_results']['errors'] ?? 0 }}</span> Errors
                                    </span>
                                    <span class="text-yellow-600 dark:text-yellow-400">
                                        <span class="font-bold">{{ $comparisonData['file1']['drc_results']['warnings'] ?? 0 }}</span> Warnings
                                    </span>
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>

                <!-- File 2 Details -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">
                        Version {{ $comparisonData['file2']['version'] }}
                        @if($comparisonData['differences']['version']['is_newer'])
                            <span class="text-sm text-green-600 dark:text-green-400">(Newer)</span>
                        @endif
                    </h3>
                    
                    <dl class="space-y-2">
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-600 dark:text-gray-400">Filename:</dt>
                            <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $comparisonData['file2']['filename'] }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-600 dark:text-gray-400">Format:</dt>
                            <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ strtoupper($comparisonData['file2']['format']) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-600 dark:text-gray-400">File Size:</dt>
                            <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ number_format($comparisonData['file2']['file_size'] / 1024 / 1024, 2) }} MB
                                @if($comparisonData['differences']['size']['direction'] !== 'unchanged')
                                    <span class="text-xs {{ $comparisonData['differences']['size']['direction'] === 'increased' ? 'text-red-600' : 'text-green-600' }}">
                                        ({{ $comparisonData['differences']['size']['direction'] === 'increased' ? '+' : '-' }}{{ $comparisonData['differences']['size']['percentage'] }}%)
                                    </span>
                                @endif
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-600 dark:text-gray-400">Uploaded:</dt>
                            <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $comparisonData['file2']['created_at']->format('Y-m-d H:i') }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-600 dark:text-gray-400">Uploaded By:</dt>
                            <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $comparisonData['file2']['uploaded_by'] }}</dd>
                        </div>
                        
                        @if($comparisonData['file2']['drc_results'])
                            <div class="pt-4 border-t">
                                <dt class="text-sm text-gray-600 dark:text-gray-400 mb-2">DRC Results:</dt>
                                <dd class="flex space-x-4">
                                    <span class="text-red-600 dark:text-red-400">
                                        <span class="font-bold">{{ $comparisonData['file2']['drc_results']['errors'] ?? 0 }}</span> Errors
                                        @if($comparisonData['differences']['drc']['errors']['diff'] !== 0)
                                            <span class="text-xs">({{ $comparisonData['differences']['drc']['errors']['diff'] > 0 ? '+' : '' }}{{ $comparisonData['differences']['drc']['errors']['diff'] }})</span>
                                        @endif
                                    </span>
                                    <span class="text-yellow-600 dark:text-yellow-400">
                                        <span class="font-bold">{{ $comparisonData['file2']['drc_results']['warnings'] ?? 0 }}</span> Warnings
                                        @if($comparisonData['differences']['drc']['warnings']['diff'] !== 0)
                                            <span class="text-xs">({{ $comparisonData['differences']['drc']['warnings']['diff'] > 0 ? '+' : '' }}{{ $comparisonData['differences']['drc']['warnings']['diff'] }})</span>
                                        @endif
                                    </span>
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Change Details -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Change Details</h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    @if($comparisonData['file1']['change_description'])
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Version {{ $comparisonData['file1']['version'] }} Changes:
                            </h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $comparisonData['file1']['change_description'] }}
                            </p>
                        </div>
                    @endif
                    
                    @if($comparisonData['file2']['change_description'])
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Version {{ $comparisonData['file2']['version'] }} Changes:
                            </h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $comparisonData['file2']['change_description'] }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Visual Comparison -->
            @if($visualComparison['available'])
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Visual Comparison</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">{{ $visualComparison['message'] }}</p>
                    
                    <div class="bg-gray-100 dark:bg-gray-700 rounded p-8 text-center">
                        <x-heroicon-o-eye class="w-12 h-12 mx-auto text-gray-400 mb-2" />
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Visual PCB comparison would be displayed here using integrated PCB viewer
                        </p>
                    </div>
                </div>
            @else
                <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4">
                    <p class="text-sm text-yellow-800 dark:text-yellow-200">
                        <x-heroicon-o-information-circle class="w-5 h-5 inline mr-1" />
                        {{ $visualComparison['message'] }}
                    </p>
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>