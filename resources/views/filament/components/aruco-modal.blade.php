<div class="text-center p-6">
    <h3 class="text-lg font-semibold mb-4">{{ $component->name }}</h3>
    
    @if($component->aruco_image_path && Storage::disk('public')->exists($component->aruco_image_path))
        <img src="{{ Storage::disk('public')->url($component->aruco_image_path) }}" 
             alt="{{ $component->aruco_code }}" 
             class="mx-auto mb-4"
             style="max-width: 300px;">
    @else
        <div class="bg-gray-100 p-8 rounded-lg mb-4">
            <p class="text-gray-500">ArUco image not available</p>
        </div>
    @endif
    
    <div class="space-y-2 text-left max-w-md mx-auto">
        <div class="flex justify-between">
            <span class="font-medium">ArUco Code:</span>
            <span>{{ $component->aruco_code }}</span>
        </div>
        <div class="flex justify-between">
            <span class="font-medium">Part Number:</span>
            <span>{{ $component->manufacturer_part_number }}</span>
        </div>
        <div class="flex justify-between">
            <span class="font-medium">Manufacturer:</span>
            <span>{{ $component->manufacturer }}</span>
        </div>
        <div class="flex justify-between">
            <span class="font-medium">Stock:</span>
            <span>{{ $component->stock_quantity }} units</span>
        </div>
        <div class="flex justify-between">
            <span class="font-medium">Location:</span>
            <span>{{ $component->storage_location ?? 'Not specified' }}</span>
        </div>
    </div>
    
    <div class="mt-6">
        <a href="{{ Storage::disk('public')->url($component->aruco_image_path) }}" 
           download="{{ $component->aruco_code }}.png" 
           class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
            <x-heroicon-o-arrow-down-tray class="w-5 h-5 mr-2" />
            Download ArUco Code
        </a>
    </div>
</div>