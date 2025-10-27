/**
 * Supernova Mobile App - ArUco Scanner & Assembly Management
 * Handles PWA installation, camera access, ArUco detection, and API integration
 */

class SupernovaMobileApp {
    constructor() {
        this.isScanning = false;
        this.currentCamera = 'environment'; // 'user' or 'environment'
        this.stream = null;
        this.canvas = null;
        this.context = null;
        this.detector = null;
        this.qrScanner = null;
        this.scanMode = 'aruco'; // 'aruco' or 'qr'
        this.apiBase = '/api/mobile';
        
        this.init();
    }

    async init() {
        console.log('🚀 Initializing Supernova Mobile App');
        
        // Initialize UI
        this.initTabNavigation();
        this.initPWA();
        this.initScanner();
        this.initFAB();
        this.initOfflineSupport();
        
        // Load data
        await this.loadInitialData();
        
        console.log('✅ Supernova Mobile App initialized');
    }

    // ==================== PWA Installation ====================
    
    initPWA() {
        let deferredPrompt;
        const installPrompt = document.getElementById('pwa-install-prompt');
        const installBtn = document.getElementById('pwa-install-accept');
        const dismissBtn = document.getElementById('pwa-install-dismiss');

        // Handle beforeinstallprompt event
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            
            // Show install prompt if not already installed
            if (!window.matchMedia('(display-mode: standalone)').matches) {
                installPrompt.classList.remove('hidden');
            }
        });

        // Install button click
        installBtn.addEventListener('click', async () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                console.log(`PWA install: ${outcome}`);
                deferredPrompt = null;
            }
            installPrompt.classList.add('hidden');
        });

        // Dismiss button click
        dismissBtn.addEventListener('click', () => {
            installPrompt.classList.add('hidden');
        });

        // Hide install prompt if already installed
        window.addEventListener('appinstalled', () => {
            installPrompt.classList.add('hidden');
            console.log('PWA was installed');
        });
    }

    // ==================== Tab Navigation ====================
    
    initTabNavigation() {
        const tabs = document.querySelectorAll('.nav-tab');
        const contents = document.querySelectorAll('.tab-content');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const targetTab = tab.dataset.tab;
                
                // Update active tab
                tabs.forEach(t => {
                    t.classList.remove('border-emerald-500', 'text-emerald-400');
                    t.classList.add('border-transparent', 'text-gray-400');
                });
                tab.classList.remove('border-transparent', 'text-gray-400');
                tab.classList.add('border-emerald-500', 'text-emerald-400');
                
                // Show target content
                contents.forEach(content => {
                    content.classList.add('hidden');
                });
                document.getElementById(`${targetTab}-tab`).classList.remove('hidden');
                
                // Handle tab-specific initialization
                this.handleTabSwitch(targetTab);
            });
        });
    }

    handleTabSwitch(tab) {
        switch (tab) {
            case 'scanner':
                this.startCamera();
                break;
            case 'inventory':
                this.loadInventory();
                break;
            case 'checklist':
                this.loadChecklists();
                break;
            case 'projects':
                this.loadProjects();
                break;
        }
    }

    // ==================== Scanner Functionality ====================
    
    initScanner() {
        const canvas = document.getElementById('scanner-canvas');
        this.canvas = canvas;
        this.context = canvas.getContext('2d');
        
        // Scanner mode buttons
        document.getElementById('scanner-mode-aruco').addEventListener('click', () => {
            this.setScanMode('aruco');
        });
        
        document.getElementById('scanner-mode-qr').addEventListener('click', () => {
            this.setScanMode('qr');
        });
        
        // Camera controls
        document.getElementById('toggle-camera').addEventListener('click', () => {
            this.toggleCamera();
        });
        
        document.getElementById('capture-photo').addEventListener('click', () => {
            this.capturePhoto();
        });
        
        document.getElementById('toggle-flashlight').addEventListener('click', () => {
            this.toggleFlashlight();
        });
        
        // Start camera on load
        this.startCamera();
    }

    setScanMode(mode) {
        this.scanMode = mode;
        
        // Update UI
        const modes = document.querySelectorAll('.scanner-mode');
        modes.forEach(btn => {
            btn.classList.remove('bg-emerald-600', 'text-white');
            btn.classList.add('text-gray-400');
        });
        
        const activeBtn = document.getElementById(`scanner-mode-${mode}`);
        activeBtn.classList.add('bg-emerald-600', 'text-white');
        activeBtn.classList.remove('text-gray-400');
        
        // Stop current scanner and restart with new mode
        this.stopScanning();
        setTimeout(() => this.startScanning(), 100);
        
        console.log(`Scanner mode changed to: ${mode}`);
    }

    async startCamera() {
        try {
            const video = document.getElementById('camera-preview');
            
            // Stop existing stream
            if (this.stream) {
                this.stream.getTracks().forEach(track => track.stop());
            }
            
            // Request camera access
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: this.currentCamera,
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                },
                audio: false
            });
            
            video.srcObject = this.stream;
            video.play();
            
            // Resize canvas to match video
            video.addEventListener('loadedmetadata', () => {
                this.canvas.width = video.videoWidth;
                this.canvas.height = video.videoHeight;
                this.startScanning();
            });
            
            console.log('📹 Camera started');
            
        } catch (error) {
            console.error('❌ Camera access failed:', error);
            this.showNotification('Camera access denied. Please enable camera permissions.', 'error');
        }
    }

    async startScanning() {
        if (this.isScanning) return;
        
        this.isScanning = true;
        const video = document.getElementById('camera-preview');
        
        if (this.scanMode === 'aruco') {
            this.startArUcoScanning(video);
        } else {
            this.startQRScanning();
        }
    }

    startArUcoScanning(video) {
        const scanFrame = () => {
            if (!this.isScanning) return;
            
            // Draw video frame to canvas
            this.context.drawImage(video, 0, 0, this.canvas.width, this.canvas.height);
            
            // Get image data for ArUco detection
            const imageData = this.context.getImageData(0, 0, this.canvas.width, this.canvas.height);
            
            // Detect ArUco markers (simplified - would need proper ArUco.js integration)
            this.detectArUcoMarkers(imageData);
            
            // Continue scanning
            requestAnimationFrame(scanFrame);
        };
        
        scanFrame();
    }

    async startQRScanning() {
        try {
            if (this.qrScanner) {
                this.qrScanner.stop();
            }
            
            this.qrScanner = new Html5Qrcode("camera-preview");
            
            await this.qrScanner.start(
                { facingMode: this.currentCamera },
                {
                    fps: 10,
                    qrbox: { width: 250, height: 250 }
                },
                (decodedText, decodedResult) => {
                    this.handleScanResult('qr', decodedText, decodedResult);
                },
                (errorMessage) => {
                    // QR scanning error (ignore - happens frequently)
                }
            );
            
        } catch (error) {
            console.error('❌ QR Scanner failed:', error);
        }
    }

    detectArUcoMarkers(imageData) {
        // Simplified ArUco detection - in real implementation would use OpenCV.js
        // This is a placeholder that demonstrates the structure
        
        // For now, simulate marker detection occasionally
        if (Math.random() < 0.1) { // 10% chance per frame
            const mockMarker = {
                id: Math.floor(Math.random() * 1000),
                corners: [
                    [100, 100], [200, 100], [200, 200], [100, 200]
                ]
            };
            
            this.handleScanResult('aruco', mockMarker.id, mockMarker);
        }
    }

    stopScanning() {
        this.isScanning = false;
        
        if (this.qrScanner) {
            this.qrScanner.stop().catch(console.error);
            this.qrScanner = null;
        }
    }

    async handleScanResult(type, data, result) {
        console.log(`📱 ${type.toUpperCase()} scan result:`, data);
        
        // Vibrate on successful scan
        if (navigator.vibrate) {
            navigator.vibrate(200);
        }
        
        // Process scan result
        if (type === 'aruco') {
            await this.processArUcoScan(data, result);
        } else {
            await this.processQRScan(data, result);
        }
        
        // Show result in UI
        this.displayScanResult(type, data, result);
    }

    async processArUcoScan(markerId, markerData) {
        try {
            // Check if this is a component marker
            const response = await fetch(`${this.apiBase}/aruco/${markerId}`);
            
            if (response.ok) {
                const componentData = await response.json();
                this.showComponentDetails(componentData);
            } else {
                this.showNotification(`ArUco marker ${markerId} not found in system`, 'warning');
            }
            
        } catch (error) {
            console.error('❌ ArUco processing failed:', error);
            this.showNotification('Failed to process ArUco marker', 'error');
        }
    }

    async processQRScan(qrData, result) {
        try {
            // Handle different QR code formats
            if (qrData.startsWith('http')) {
                // URL QR code
                this.showNotification(`QR Code: ${qrData}`, 'info');
            } else if (qrData.includes('component:')) {
                // Component QR code
                const componentId = qrData.replace('component:', '');
                const response = await fetch(`${this.apiBase}/component/${componentId}`);
                
                if (response.ok) {
                    const componentData = await response.json();
                    this.showComponentDetails(componentData);
                }
            } else {
                // Generic QR code
                this.showNotification(`QR Code: ${qrData}`, 'info');
            }
            
        } catch (error) {
            console.error('❌ QR processing failed:', error);
            this.showNotification('Failed to process QR code', 'error');
        }
    }

    displayScanResult(type, data, result) {
        const resultsContainer = document.getElementById('scan-results');
        
        const resultElement = document.createElement('div');
        resultElement.className = 'bg-gray-800 rounded-lg p-4 border border-gray-700';
        resultElement.innerHTML = `
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-emerald-400">${type.toUpperCase()} Detected</span>
                <span class="text-xs text-gray-400">${new Date().toLocaleTimeString()}</span>
            </div>
            <div class="text-white">${JSON.stringify(data)}</div>
        `;
        
        resultsContainer.insertBefore(resultElement, resultsContainer.firstChild);
        
        // Keep only last 10 results
        while (resultsContainer.children.length > 10) {
            resultsContainer.removeChild(resultsContainer.lastChild);
        }
    }

    toggleCamera() {
        this.currentCamera = this.currentCamera === 'environment' ? 'user' : 'environment';
        this.startCamera();
    }

    capturePhoto() {
        const video = document.getElementById('camera-preview');
        
        // Create canvas for photo capture
        const photoCanvas = document.createElement('canvas');
        photoCanvas.width = video.videoWidth;
        photoCanvas.height = video.videoHeight;
        
        const photoContext = photoCanvas.getContext('2d');
        photoContext.drawImage(video, 0, 0);
        
        // Convert to blob and save
        photoCanvas.toBlob((blob) => {
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `supernova-scan-${Date.now()}.jpg`;
            a.click();
            URL.revokeObjectURL(url);
        }, 'image/jpeg', 0.9);
        
        this.showNotification('Photo captured and saved', 'success');
    }

    async toggleFlashlight() {
        try {
            const track = this.stream.getVideoTracks()[0];
            const capabilities = track.getCapabilities();
            
            if (capabilities.torch) {
                const settings = track.getSettings();
                await track.applyConstraints({
                    advanced: [{ torch: !settings.torch }]
                });
                
                this.showNotification(
                    settings.torch ? 'Flashlight off' : 'Flashlight on', 
                    'info'
                );
            } else {
                this.showNotification('Flashlight not available', 'warning');
            }
            
        } catch (error) {
            console.error('❌ Flashlight toggle failed:', error);
            this.showNotification('Failed to toggle flashlight', 'error');
        }
    }

    // ==================== Data Loading ====================
    
    async loadInitialData() {
        console.log('📊 Loading initial data...');
        
        try {
            // Load in parallel
            await Promise.all([
                this.loadInventory(),
                this.loadChecklists(),
                this.loadProjects()
            ]);
            
            console.log('✅ Initial data loaded');
            
        } catch (error) {
            console.error('❌ Failed to load initial data:', error);
            this.showNotification('Failed to load data. Check connection.', 'error');
        }
    }

    async loadInventory() {
        try {
            const response = await fetch(`${this.apiBase}/inventory`);
            const components = await response.json();
            
            this.displayInventory(components);
            
        } catch (error) {
            console.error('❌ Failed to load inventory:', error);
        }
    }

    async loadChecklists() {
        try {
            const response = await fetch(`${this.apiBase}/checklists`);
            const checklists = await response.json();
            
            this.displayChecklists(checklists);
            
        } catch (error) {
            console.error('❌ Failed to load checklists:', error);
        }
    }

    async loadProjects() {
        try {
            const response = await fetch(`${this.apiBase}/projects`);
            const projects = await response.json();
            
            this.displayProjects(projects);
            
        } catch (error) {
            console.error('❌ Failed to load projects:', error);
        }
    }

    // ==================== UI Display Methods ====================
    
    displayInventory(components) {
        const container = document.getElementById('inventory-list');
        
        if (!components || components.length === 0) {
            container.innerHTML = '<p class="text-gray-400 text-center py-8">No components found</p>';
            return;
        }
        
        container.innerHTML = components.map(component => `
            <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
                <div class="flex justify-between items-start mb-2">
                    <h3 class="font-semibold text-white">${component.name}</h3>
                    <span class="text-sm px-2 py-1 bg-emerald-600 rounded">${component.quantity}</span>
                </div>
                <p class="text-sm text-gray-400">${component.description || 'No description'}</p>
                <div class="flex justify-between items-center mt-3">
                    <span class="text-xs text-gray-500">${component.category}</span>
                    <button class="text-emerald-400 text-sm" onclick="app.viewComponent(${component.id})">
                        View Details
                    </button>
                </div>
            </div>
        `).join('');
    }

    displayChecklists(checklists) {
        const container = document.getElementById('checklist-list');
        
        if (!checklists || checklists.length === 0) {
            container.innerHTML = '<p class="text-gray-400 text-center py-8">No active checklists</p>';
            return;
        }
        
        container.innerHTML = checklists.map(checklist => `
            <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
                <div class="flex justify-between items-start mb-2">
                    <h3 class="font-semibold text-white">${checklist.template_name}</h3>
                    <span class="text-sm px-2 py-1 bg-blue-600 rounded">${checklist.status}</span>
                </div>
                <p class="text-sm text-gray-400">Board: ${checklist.board_serial_number}</p>
                <div class="mt-3">
                    <div class="flex justify-between text-sm text-gray-400 mb-1">
                        <span>Progress</span>
                        <span>${checklist.completion_percentage}%</span>
                    </div>
                    <div class="w-full bg-gray-700 rounded-full h-2">
                        <div class="bg-emerald-600 h-2 rounded-full" style="width: ${checklist.completion_percentage}%"></div>
                    </div>
                </div>
                <button class="mt-3 w-full bg-emerald-600 hover:bg-emerald-700 text-white py-2 rounded" 
                        onclick="app.openChecklist(${checklist.id})">
                    Continue Checklist
                </button>
            </div>
        `).join('');
    }

    displayProjects(projects) {
        const container = document.getElementById('project-list');
        
        if (!projects || projects.length === 0) {
            container.innerHTML = '<p class="text-gray-400 text-center py-8">No projects found</p>';
            return;
        }
        
        container.innerHTML = projects.map(project => `
            <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
                <div class="flex justify-between items-start mb-2">
                    <h3 class="font-semibold text-white">${project.name}</h3>
                    <span class="text-sm px-2 py-1 bg-yellow-600 rounded">${project.status}</span>
                </div>
                <p class="text-sm text-gray-400">${project.description || 'No description'}</p>
                <div class="flex justify-between items-center mt-3">
                    <span class="text-xs text-gray-500">Customer: ${project.customer_name}</span>
                    <button class="text-emerald-400 text-sm" onclick="app.viewProject(${project.id})">
                        View Details
                    </button>
                </div>
            </div>
        `).join('');
    }

    // ==================== Action Methods ====================
    
    showComponentDetails(component) {
        // Show component details modal or navigate to component page
        console.log('Showing component details:', component);
        this.showNotification(`Component: ${component.name}`, 'info');
    }

    viewComponent(id) {
        console.log('Viewing component:', id);
        // Implement component detail view
    }

    openChecklist(id) {
        console.log('Opening checklist:', id);
        // Implement checklist interface
    }

    viewProject(id) {
        console.log('Viewing project:', id);
        // Implement project detail view
    }

    // ==================== Utility Methods ====================
    
    initFAB() {
        const fab = document.getElementById('fab');
        fab.addEventListener('click', () => {
            // Show action menu or quick add
            this.showNotification('Quick actions coming soon!', 'info');
        });
    }

    initOfflineSupport() {
        // Check online status
        const updateConnectionStatus = () => {
            const status = document.getElementById('connection-status');
            if (navigator.onLine) {
                status.className = 'w-3 h-3 bg-green-500 rounded-full';
                status.title = 'Online';
            } else {
                status.className = 'w-3 h-3 bg-red-500 rounded-full';
                status.title = 'Offline';
            }
        };

        window.addEventListener('online', updateConnectionStatus);
        window.addEventListener('offline', updateConnectionStatus);
        updateConnectionStatus();

        // Sync button
        document.getElementById('sync-btn').addEventListener('click', () => {
            this.syncData();
        });
    }

    async syncData() {
        this.showNotification('Syncing data...', 'info');
        
        try {
            await this.loadInitialData();
            this.showNotification('Sync completed', 'success');
        } catch (error) {
            this.showNotification('Sync failed', 'error');
        }
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `fixed top-20 left-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
            type === 'success' ? 'bg-green-600' :
            type === 'error' ? 'bg-red-600' :
            type === 'warning' ? 'bg-yellow-600' :
            'bg-blue-600'
        }`;
        notification.innerHTML = `
            <div class="flex items-center justify-between">
                <span class="text-white">${message}</span>
                <button class="ml-4 text-white opacity-75 hover:opacity-100" onclick="this.parentElement.parentElement.remove()">
                    ✕
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }
}

// Initialize app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.app = new SupernovaMobileApp();
});

// Service Worker registration
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then((registration) => {
                console.log('SW registered: ', registration);
            })
            .catch((registrationError) => {
                console.log('SW registration failed: ', registrationError);
            });
    });
}