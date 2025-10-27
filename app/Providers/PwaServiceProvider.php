<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class PwaServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Share PWA meta tags with all views
        View::composer('*', function ($view) {
            $view->with('pwaMetaTags', $this->getPwaMetaTags());
        });

        // Register PWA routes
        $this->registerPwaRoutes();
    }

    /**
     * Get PWA meta tags
     */
    protected function getPwaMetaTags(): string
    {
        return <<<'HTML'
        <!-- PWA Meta Tags -->
        <meta name="theme-color" content="#f59e0b">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="Supernova">
        <meta name="application-name" content="Supernova Management">
        
        <!-- Icons -->
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon.png">
        <link rel="apple-touch-icon" sizes="72x72" href="/icons/icon-72x72.png">
        <link rel="apple-touch-icon" sizes="96x96" href="/icons/icon-96x96.png">
        <link rel="apple-touch-icon" sizes="128x128" href="/icons/icon-128x128.png">
        <link rel="apple-touch-icon" sizes="144x144" href="/icons/icon-144x144.png">
        <link rel="apple-touch-icon" sizes="152x152" href="/icons/icon-152x152.png">
        <link rel="apple-touch-icon" sizes="192x192" href="/icons/icon-192x192.png">
        <link rel="apple-touch-icon" sizes="384x384" href="/icons/icon-384x384.png">
        <link rel="apple-touch-icon" sizes="512x512" href="/icons/icon-512x512.png">
        
        <!-- Manifest -->
        <link rel="manifest" href="/manifest.json">
        
        <!-- Service Worker Registration -->
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function() {
                    navigator.serviceWorker.register('/sw.js').then(function(registration) {
                        console.log('ServiceWorker registration successful');
                        
                        // Check for updates
                        registration.addEventListener('updatefound', () => {
                            const newWorker = registration.installing;
                            newWorker.addEventListener('statechange', () => {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    // New update available
                                    if (confirm('Nuova versione disponibile! Vuoi aggiornare?')) {
                                        newWorker.postMessage({ type: 'SKIP_WAITING' });
                                        window.location.reload();
                                    }
                                }
                            });
                        });
                    }, function(err) {
                        console.log('ServiceWorker registration failed: ', err);
                    });
                });
            }
            
            // Install prompt
            let deferredPrompt;
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                deferredPrompt = e;
                
                // Show custom install button
                const installButton = document.getElementById('pwa-install-button');
                if (installButton) {
                    installButton.style.display = 'block';
                    installButton.addEventListener('click', async () => {
                        deferredPrompt.prompt();
                        const { outcome } = await deferredPrompt.userChoice;
                        console.log(`User response to the install prompt: ${outcome}`);
                        deferredPrompt = null;
                        installButton.style.display = 'none';
                    });
                }
            });
            
            // iOS install instructions
            if (navigator.standalone === false && /iPhone|iPad|iPod/.test(navigator.userAgent)) {
                // Show iOS install instructions
                const iosPrompt = document.createElement('div');
                iosPrompt.innerHTML = `
                    <div style="position: fixed; bottom: 20px; left: 20px; right: 20px; background: #f59e0b; color: white; padding: 15px; border-radius: 8px; z-index: 9999; display: flex; align-items: center; gap: 10px;">
                        <div style="flex: 1;">
                            <strong>Installa Supernova</strong><br>
                            Tocca <svg style="width: 20px; height: 20px; vertical-align: middle;" fill="currentColor" viewBox="0 0 20 20"><path d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L11 6.414V15a1 1 0 11-2 0V6.414L7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zM3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"/></svg> e poi "Aggiungi a Home"
                        </div>
                        <button onclick="this.parentElement.remove()" style="background: rgba(255,255,255,0.2); border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">✕</button>
                    </div>
                `;
                
                // Show prompt after 30 seconds
                setTimeout(() => {
                    if (!localStorage.getItem('ios-install-prompted')) {
                        document.body.appendChild(iosPrompt);
                        localStorage.setItem('ios-install-prompted', 'true');
                    }
                }, 30000);
            }
        </script>
        HTML;
    }

    /**
     * Register PWA routes
     */
    protected function registerPwaRoutes(): void
    {
        // Add routes for PWA assets if needed
    }
}