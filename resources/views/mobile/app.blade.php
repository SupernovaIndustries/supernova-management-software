<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1f2937">
    <title>Supernova Mobile - Assembly & Scanner</title>
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Supernova">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192x192.png') }}">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Camera & ArUco Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/opencv.js@4.8.0/opencv.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/aruco-js@1.0.0/aruco.js"></script>
    
    <!-- QR Code Scanner Library -->
    <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    
    <style>
        /* Custom styles for mobile */
        .scanner-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            pointer-events: none;
        }
        
        .scanner-viewfinder {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 250px;
            height: 250px;
            border: 2px solid #10b981;
            border-radius: 8px;
            box-shadow: 0 0 0 1000px rgba(0, 0, 0, 0.5);
        }
        
        .scanner-corner {
            position: absolute;
            width: 20px;
            height: 20px;
            border: 3px solid #10b981;
        }
        
        .scanner-corner.top-left {
            top: -3px;
            left: -3px;
            border-right: none;
            border-bottom: none;
        }
        
        .scanner-corner.top-right {
            top: -3px;
            right: -3px;
            border-left: none;
            border-bottom: none;
        }
        
        .scanner-corner.bottom-left {
            bottom: -3px;
            left: -3px;
            border-right: none;
            border-top: none;
        }
        
        .scanner-corner.bottom-right {
            bottom: -3px;
            right: -3px;
            border-left: none;
            border-top: none;
        }
        
        .pwa-install-prompt {
            position: fixed;
            bottom: 20px;
            left: 20px;
            right: 20px;
            z-index: 1000;
        }
    </style>
</head>
<body class="bg-gray-900 text-white overflow-x-hidden">
    <!-- PWA Install Prompt -->
    <div id="pwa-install-prompt" class="pwa-install-prompt hidden">
        <div class="bg-blue-600 rounded-lg p-4 flex items-center justify-between shadow-lg">
            <div class="flex-1">
                <h3 class="font-semibold">Install Supernova App</h3>
                <p class="text-sm text-blue-100">Add to home screen for better experience</p>
            </div>
            <div class="flex gap-2">
                <button id="pwa-install-dismiss" class="px-3 py-1 text-sm text-blue-100 hover:text-white">Later</button>
                <button id="pwa-install-accept" class="px-4 py-2 bg-white text-blue-600 rounded font-medium text-sm">Install</button>
            </div>
        </div>
    </div>

    <!-- Header -->
    <header class="bg-gray-800 shadow-lg sticky top-0 z-50">
        <div class="px-4 py-3">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold text-white">Supernova Mobile</h1>
                <div class="flex items-center gap-2">
                    <span id="connection-status" class="w-3 h-3 bg-green-500 rounded-full" title="Online"></span>
                    <button id="sync-btn" class="p-2 hover:bg-gray-700 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Navigation Tabs -->
    <nav class="bg-gray-800 border-t border-gray-700">
        <div class="flex">
            <button class="nav-tab flex-1 py-3 px-4 text-center border-b-2 border-emerald-500 text-emerald-400 font-medium" data-tab="scanner">
                <svg class="w-6 h-6 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                Scanner
            </button>
            <button class="nav-tab flex-1 py-3 px-4 text-center border-b-2 border-transparent text-gray-400 hover:text-white" data-tab="inventory">
                <svg class="w-6 h-6 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
                Inventory
            </button>
            <button class="nav-tab flex-1 py-3 px-4 text-center border-b-2 border-transparent text-gray-400 hover:text-white" data-tab="checklist">
                <svg class="w-6 h-6 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                </svg>
                Checklist
            </button>
            <button class="nav-tab flex-1 py-3 px-4 text-center border-b-2 border-transparent text-gray-400 hover:text-white" data-tab="projects">
                <svg class="w-6 h-6 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
                Projects
            </button>
        </div>
    </nav>

    <!-- Content Area -->
    <main class="min-h-screen bg-gray-900">
        <!-- Scanner Tab -->
        <div id="scanner-tab" class="tab-content">
            <div class="p-4">
                <div class="mb-4">
                    <h2 class="text-2xl font-bold mb-2">ArUco & Barcode Scanner</h2>
                    <p class="text-gray-400">Scan components, QR codes, and assembly markers</p>
                </div>

                <!-- Scanner Mode Selector -->
                <div class="mb-4">
                    <div class="bg-gray-800 rounded-lg p-1 flex">
                        <button id="scanner-mode-aruco" class="scanner-mode flex-1 py-2 px-4 rounded text-center bg-emerald-600 text-white font-medium">
                            ArUco Markers
                        </button>
                        <button id="scanner-mode-qr" class="scanner-mode flex-1 py-2 px-4 rounded text-center text-gray-400 hover:text-white">
                            QR/Barcode
                        </button>
                    </div>
                </div>

                <!-- Camera Preview -->
                <div class="relative bg-black rounded-lg overflow-hidden mb-4" style="aspect-ratio: 4/3;">
                    <video id="camera-preview" class="w-full h-full object-cover" autoplay muted playsinline></video>
                    <canvas id="scanner-canvas" class="absolute top-0 left-0 w-full h-full pointer-events-none"></canvas>
                    
                    <!-- Scanner Overlay -->
                    <div class="scanner-overlay">
                        <div class="scanner-viewfinder">
                            <div class="scanner-corner top-left"></div>
                            <div class="scanner-corner top-right"></div>
                            <div class="scanner-corner bottom-left"></div>
                            <div class="scanner-corner bottom-right"></div>
                        </div>
                    </div>

                    <!-- Scanner Controls -->
                    <div class="absolute bottom-4 left-0 right-0 flex justify-center gap-4">
                        <button id="toggle-camera" class="bg-gray-800 bg-opacity-75 hover:bg-opacity-100 p-3 rounded-full">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                        </button>
                        <button id="capture-photo" class="bg-emerald-600 hover:bg-emerald-700 p-4 rounded-full">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </button>
                        <button id="toggle-flashlight" class="bg-gray-800 bg-opacity-75 hover:bg-opacity-100 p-3 rounded-full">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Scan Results -->
                <div id="scan-results" class="space-y-3">
                    <!-- Results will be populated by JavaScript -->
                </div>
            </div>
        </div>

        <!-- Inventory Tab -->
        <div id="inventory-tab" class="tab-content hidden">
            <div class="p-4">
                <h2 class="text-2xl font-bold mb-4">Quick Inventory</h2>
                
                <!-- Search -->
                <div class="mb-4">
                    <input type="text" id="inventory-search" placeholder="Search components..." 
                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 text-white placeholder-gray-400 focus:border-emerald-500 focus:outline-none">
                </div>

                <!-- Component List -->
                <div id="inventory-list" class="space-y-3">
                    <!-- Components will be loaded by JavaScript -->
                </div>
            </div>
        </div>

        <!-- Checklist Tab -->
        <div id="checklist-tab" class="tab-content hidden">
            <div class="p-4">
                <h2 class="text-2xl font-bold mb-4">Assembly Checklists</h2>
                
                <!-- Active Checklists -->
                <div id="checklist-list" class="space-y-3">
                    <!-- Checklists will be loaded by JavaScript -->
                </div>
            </div>
        </div>

        <!-- Projects Tab -->
        <div id="projects-tab" class="tab-content hidden">
            <div class="p-4">
                <h2 class="text-2xl font-bold mb-4">Projects</h2>
                
                <!-- Project List -->
                <div id="project-list" class="space-y-3">
                    <!-- Projects will be loaded by JavaScript -->
                </div>
            </div>
        </div>
    </main>

    <!-- Floating Action Button -->
    <button id="fab" class="fixed bottom-6 right-6 bg-emerald-600 hover:bg-emerald-700 p-4 rounded-full shadow-lg z-40">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
        </svg>
    </button>

    <!-- Scripts -->
    <script src="{{ asset('js/mobile-app.js') }}"></script>
</body>
</html>