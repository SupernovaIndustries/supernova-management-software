<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectPwaAssets
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only inject into HTML responses
        if ($response instanceof \Illuminate\Http\Response && 
            str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
            
            $content = $response->getContent();
            
            // Inject PWA meta tags before </head>
            $pwaMetaTags = $this->getPwaMetaTags();
            $content = str_replace('</head>', $pwaMetaTags . '</head>', $content);
            
            // Inject install button in body
            $installButton = $this->getInstallButton();
            $content = str_replace('<body', '<body>' . $installButton . '<body', $content);
            
            $response->setContent($content);
        }

        return $response;
    }

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
                
                // Show install button
                const installBtn = document.getElementById('pwa-install-btn');
                if (installBtn) {
                    installBtn.style.display = 'flex';
                    installBtn.addEventListener('click', async () => {
                        deferredPrompt.prompt();
                        const { outcome } = await deferredPrompt.userChoice;
                        deferredPrompt = null;
                        installBtn.style.display = 'none';
                    });
                }
            });
        </script>
        HTML;
    }

    protected function getInstallButton(): string
    {
        return <<<'HTML'
        <!-- PWA Install Button -->
        <div id="pwa-install-btn" style="display: none; position: fixed; bottom: 20px; right: 20px; background: #f59e0b; color: white; padding: 12px 20px; border-radius: 50px; cursor: pointer; z-index: 9999; align-items: center; gap: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <svg style="width: 20px; height: 20px;" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L11 6.414V15a1 1 0 11-2 0V6.414L7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zM3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"/>
            </svg>
            <span>Installa App</span>
        </div>
        HTML;
    }
}