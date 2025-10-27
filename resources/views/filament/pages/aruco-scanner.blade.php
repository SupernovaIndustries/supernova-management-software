<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Camera View -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">ArUco Scanner</h3>
            
            <div class="relative">
                <video id="camera-stream" class="w-full max-w-2xl mx-auto rounded-lg bg-gray-900" style="height: 480px;"></video>
                <canvas id="camera-canvas" class="hidden"></canvas>
                
                <div class="absolute inset-0 pointer-events-none">
                    <div class="flex items-center justify-center h-full">
                        <div class="border-2 border-primary-500 rounded-lg" style="width: 300px; height: 300px;">
                            <div class="flex items-center justify-center h-full">
                                <span class="text-white bg-black bg-opacity-50 px-3 py-1 rounded">
                                    Position ArUco code here
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-4 flex justify-center space-x-4">
                <button id="start-camera" class="bg-primary-600 text-white px-4 py-2 rounded-lg hover:bg-primary-700">
                    <x-heroicon-o-play class="w-5 h-5 inline mr-2" />
                    Start Camera
                </button>
                <button id="stop-camera" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 hidden">
                    <x-heroicon-o-stop class="w-5 h-5 inline mr-2" />
                    Stop Camera
                </button>
            </div>
        </div>
        
        <!-- Manual Input -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Manual Code Entry</h3>
            
            <form wire:submit.prevent="scanCode(manualCode)" class="flex gap-4">
                <input 
                    type="text" 
                    wire:model="manualCode"
                    placeholder="Enter ArUco code (e.g., ARUCO-000001)"
                    class="flex-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                />
                <button type="submit" class="bg-primary-600 text-white px-4 py-2 rounded-lg hover:bg-primary-700">
                    <x-heroicon-o-magnifying-glass class="w-5 h-5 inline mr-2" />
                    Search
                </button>
            </form>
        </div>
        
        <!-- Scanned Component Info -->
        @if($scannedComponent)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex justify-between items-start mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Component Details</h3>
                    <button wire:click="clearScan" class="text-gray-500 hover:text-gray-700">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <dl class="space-y-2">
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-600 dark:text-gray-400">ArUco Code:</dt>
                                <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $scannedComponent->aruco_code }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-600 dark:text-gray-400">SKU:</dt>
                                <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $scannedComponent->sku }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-600 dark:text-gray-400">Part Number:</dt>
                                <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $scannedComponent->manufacturer_part_number }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-600 dark:text-gray-400">Name:</dt>
                                <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $scannedComponent->name }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-600 dark:text-gray-400">Manufacturer:</dt>
                                <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $scannedComponent->manufacturer }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-600 dark:text-gray-400">Package:</dt>
                                <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $scannedComponent->package }}</dd>
                            </div>
                        </dl>
                    </div>
                    
                    <div>
                        <dl class="space-y-2">
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-600 dark:text-gray-400">Stock:</dt>
                                <dd class="text-sm font-medium {{ $scannedComponent->isLowStock() ? 'text-red-600' : 'text-gray-900 dark:text-gray-100' }}">
                                    {{ $scannedComponent->stock_quantity }} units
                                    @if($scannedComponent->isLowStock())
                                        <span class="text-xs">(Low Stock)</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-600 dark:text-gray-400">Location:</dt>
                                <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $scannedComponent->storage_location ?? 'Not specified' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-600 dark:text-gray-400">Unit Price:</dt>
                                <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $scannedComponent->currency }} {{ number_format($scannedComponent->unit_price, 4) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-600 dark:text-gray-400">Status:</dt>
                                <dd class="text-sm font-medium">
                                    <span class="px-2 py-1 text-xs rounded-full {{ $scannedComponent->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ ucfirst($scannedComponent->status) }}
                                    </span>
                                </dd>
                            </div>
                        </dl>
                        
                        <div class="mt-4">
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Description:</p>
                            <p class="text-sm text-gray-900 dark:text-gray-100">{{ $scannedComponent->description }}</p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end space-x-4">
                    @if($scannedComponent->datasheet_url)
                        <a href="{{ $scannedComponent->datasheet_url }}" target="_blank" class="text-primary-600 hover:text-primary-700">
                            <x-heroicon-o-document-text class="w-5 h-5 inline mr-1" />
                            View Datasheet
                        </a>
                    @endif
                    <a href="{{ route('filament.admin.resources.components.edit', $scannedComponent) }}" class="text-primary-600 hover:text-primary-700">
                        <x-heroicon-o-pencil class="w-5 h-5 inline mr-1" />
                        Edit Component
                    </a>
                </div>
            </div>
        @endif
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const video = document.getElementById('camera-stream');
            const canvas = document.getElementById('camera-canvas');
            const ctx = canvas.getContext('2d');
            const startBtn = document.getElementById('start-camera');
            const stopBtn = document.getElementById('stop-camera');
            let stream = null;
            let scanning = false;
            
            startBtn.addEventListener('click', async function() {
                try {
                    stream = await navigator.mediaDevices.getUserMedia({ 
                        video: { facingMode: 'environment' } 
                    });
                    video.srcObject = stream;
                    video.play();
                    
                    startBtn.classList.add('hidden');
                    stopBtn.classList.remove('hidden');
                    scanning = true;
                    
                    // Start scanning
                    scanForArUco();
                } catch (err) {
                    console.error('Error accessing camera:', err);
                    alert('Could not access camera. Please check permissions.');
                }
            });
            
            stopBtn.addEventListener('click', function() {
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                    video.srcObject = null;
                }
                
                stopBtn.classList.add('hidden');
                startBtn.classList.remove('hidden');
                scanning = false;
            });
            
            function scanForArUco() {
                if (!scanning) return;
                
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                ctx.drawImage(video, 0, 0);
                
                // Here you would integrate with an actual ArUco detection library
                // For now, we'll simulate detection
                
                // Continue scanning
                requestAnimationFrame(scanForArUco);
            }
        });
    </script>
</x-filament-panels::page>