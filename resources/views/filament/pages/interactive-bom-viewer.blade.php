<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        @if($ibomUrl)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow" style="height: 800px;">
                <iframe 
                    src="{{ $ibomUrl }}" 
                    class="w-full h-full rounded-lg"
                    frameborder="0"
                ></iframe>
            </div>
            
            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                <div class="flex items-start">
                    <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-2 flex-shrink-0 mt-0.5" />
                    <div class="text-sm text-blue-800 dark:text-blue-200">
                        <p class="font-semibold mb-1">Interactive BOM Features:</p>
                        <ul class="list-disc list-inside space-y-1">
                            <li>Click on components to view detailed information</li>
                            <li>Use filters to show placed, sourced, or missing components</li>
                            <li>Search for specific components by reference or part number</li>
                            <li>View component placement on PCB (when integrated with Gerber viewer)</li>
                            <li>Export or print the BOM for documentation</li>
                        </ul>
                    </div>
                </div>
            </div>
        @else
            <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-8 text-center">
                <x-heroicon-o-cpu-chip class="w-16 h-16 mx-auto text-gray-400 mb-4" />
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                    No Interactive BOM Generated
                </h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Select a project and BOM version, then click "Generate Interactive BOM" to create the visualization.
                </p>
            </div>
        @endif
    </div>
</x-filament-panels::page>