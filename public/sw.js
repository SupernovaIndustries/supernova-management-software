/**
 * Supernova Mobile App Service Worker
 * Handles caching, offline support, and background sync
 */

const CACHE_NAME = 'supernova-mobile-v1.0.0';
const STATIC_CACHE = 'supernova-static-v1';
const API_CACHE = 'supernova-api-v1';

// Assets to cache on install
const STATIC_ASSETS = [
    '/mobile',
    '/manifest.json',
    '/js/mobile-app.js',
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png',
    'https://cdn.tailwindcss.com',
    'https://cdn.jsdelivr.net/npm/opencv.js@4.8.0/opencv.js',
    'https://cdn.jsdelivr.net/npm/aruco-js@1.0.0/aruco.js',
    'https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js'
];

// API endpoints to cache
const API_ENDPOINTS = [
    '/api/mobile/inventory',
    '/api/mobile/checklists',
    '/api/mobile/projects'
];

// Install event - cache static assets
self.addEventListener('install', (event) => {
    console.log('🔧 Service Worker: Installing...');
    
    event.waitUntil(
        Promise.all([
            // Cache static assets
            caches.open(STATIC_CACHE).then((cache) => {
                console.log('📦 Caching static assets');
                return cache.addAll(STATIC_ASSETS);
            }),
            
            // Skip waiting to activate immediately
            self.skipWaiting()
        ])
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
    console.log('🚀 Service Worker: Activating...');
    
    event.waitUntil(
        Promise.all([
            // Clean up old caches
            caches.keys().then((cacheNames) => {
                return Promise.all(
                    cacheNames
                        .filter((cacheName) => {
                            return cacheName !== STATIC_CACHE && 
                                   cacheName !== API_CACHE &&
                                   cacheName !== CACHE_NAME;
                        })
                        .map((cacheName) => {
                            console.log('🗑️ Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        })
                );
            }),
            
            // Take control of all pages
            self.clients.claim()
        ])
    );
});

// Fetch event - handle network requests
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Handle different types of requests
    if (request.method === 'GET') {
        if (url.pathname.startsWith('/api/mobile/')) {
            // API requests - cache with network first strategy
            event.respondWith(handleAPIRequest(request));
        } else if (STATIC_ASSETS.some(asset => url.pathname.includes(asset))) {
            // Static assets - cache first strategy
            event.respondWith(handleStaticRequest(request));
        } else if (url.pathname === '/mobile') {
            // App shell - cache first with fallback
            event.respondWith(handleAppShell(request));
        }
    }
});

// Handle API requests with network-first strategy
async function handleAPIRequest(request) {
    const cache = await caches.open(API_CACHE);
    
    try {
        // Try network first
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            // Cache successful responses
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
        
    } catch (error) {
        console.log('📱 Network failed, serving from cache:', request.url);
        
        // Network failed, try cache
        const cachedResponse = await cache.match(request);
        
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // No cache available, return offline response
        return new Response(
            JSON.stringify({
                error: 'Offline',
                message: 'This data is not available offline'
            }),
            {
                status: 503,
                statusText: 'Service Unavailable',
                headers: { 'Content-Type': 'application/json' }
            }
        );
    }
}

// Handle static assets with cache-first strategy
async function handleStaticRequest(request) {
    const cache = await caches.open(STATIC_CACHE);
    const cachedResponse = await cache.match(request);
    
    if (cachedResponse) {
        return cachedResponse;
    }
    
    // Not in cache, fetch from network
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
        
    } catch (error) {
        console.log('❌ Failed to fetch:', request.url);
        
        // Return offline fallback for critical assets
        if (request.url.includes('.js')) {
            return new Response('// Offline fallback', {
                headers: { 'Content-Type': 'application/javascript' }
            });
        }
        
        throw error;
    }
}

// Handle app shell requests
async function handleAppShell(request) {
    const cache = await caches.open(STATIC_CACHE);
    const cachedResponse = await cache.match('/mobile');
    
    if (cachedResponse) {
        return cachedResponse;
    }
    
    // Fallback to network
    return fetch(request);
}

// Background sync for offline actions
self.addEventListener('sync', (event) => {
    console.log('🔄 Background sync:', event.tag);
    
    if (event.tag === 'background-sync') {
        event.waitUntil(doBackgroundSync());
    }
});

async function doBackgroundSync() {
    try {
        // Get pending sync data from IndexedDB
        const syncData = await getPendingSyncData();
        
        for (const item of syncData) {
            try {
                await syncItem(item);
                await removeSyncItem(item.id);
            } catch (error) {
                console.log('❌ Sync failed for item:', item.id, error);
            }
        }
        
        // Notify clients of sync completion
        self.clients.matchAll().then(clients => {
            clients.forEach(client => {
                client.postMessage({
                    type: 'SYNC_COMPLETE',
                    timestamp: Date.now()
                });
            });
        });
        
    } catch (error) {
        console.log('❌ Background sync failed:', error);
    }
}

// Push notifications
self.addEventListener('push', (event) => {
    console.log('📬 Push notification received');
    
    const options = {
        body: event.data ? event.data.text() : 'New notification from Supernova',
        icon: '/icons/icon-192x192.png',
        badge: '/icons/badge-72x72.png',
        vibrate: [200, 100, 200],
        data: {
            url: '/mobile'
        },
        actions: [
            {
                action: 'open',
                title: 'Open App',
                icon: '/icons/open-action.png'
            },
            {
                action: 'close',
                title: 'Close',
                icon: '/icons/close-action.png'
            }
        ]
    };
    
    event.waitUntil(
        self.registration.showNotification('Supernova Mobile', options)
    );
});

// Handle notification clicks
self.addEventListener('notificationclick', (event) => {
    console.log('🔔 Notification clicked:', event.action);
    
    event.notification.close();
    
    if (event.action === 'open' || !event.action) {
        event.waitUntil(
            clients.openWindow('/mobile')
        );
    }
});

// Utility functions for IndexedDB operations
async function getPendingSyncData() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('SupernovaSyncDB', 1);
        
        request.onerror = () => reject(request.error);
        
        request.onsuccess = () => {
            const db = request.result;
            const transaction = db.transaction(['pendingSync'], 'readonly');
            const store = transaction.objectStore('pendingSync');
            const getAllRequest = store.getAll();
            
            getAllRequest.onsuccess = () => resolve(getAllRequest.result);
            getAllRequest.onerror = () => reject(getAllRequest.error);
        };
        
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains('pendingSync')) {
                const store = db.createObjectStore('pendingSync', { keyPath: 'id', autoIncrement: true });
                store.createIndex('timestamp', 'timestamp', { unique: false });
            }
        };
    });
}

async function syncItem(item) {
    const response = await fetch(item.url, {
        method: item.method || 'POST',
        headers: {
            'Content-Type': 'application/json',
            ...item.headers
        },
        body: JSON.stringify(item.data)
    });
    
    if (!response.ok) {
        throw new Error(`Sync failed: ${response.status}`);
    }
    
    return response.json();
}

async function removeSyncItem(id) {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('SupernovaSyncDB', 1);
        
        request.onsuccess = () => {
            const db = request.result;
            const transaction = db.transaction(['pendingSync'], 'readwrite');
            const store = transaction.objectStore('pendingSync');
            const deleteRequest = store.delete(id);
            
            deleteRequest.onsuccess = () => resolve();
            deleteRequest.onerror = () => reject(deleteRequest.error);
        };
    });
}

// Cache management
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    } else if (event.data && event.data.type === 'CLEAR_CACHE') {
        event.waitUntil(
            caches.keys().then((cacheNames) => {
                return Promise.all(
                    cacheNames.map((cacheName) => caches.delete(cacheName))
                );
            })
        );
    }
});

console.log('🔧 Supernova Mobile Service Worker loaded');