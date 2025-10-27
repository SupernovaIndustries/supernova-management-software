<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arricchimento Componenti con Datasheet</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 antialiased">
    <div class="min-h-screen py-8">
        <div class="container mx-auto px-4">
            <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-lg p-8">
                <div class="flex items-center mb-6">
                    <a href="/admin/components" class="text-blue-600 hover:text-blue-800 mr-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </a>
                    <h1 class="text-3xl font-bold text-gray-900">🔍 Arricchimento Componenti</h1>
                </div>

                <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        <div class="flex-1">
                            <p class="text-sm text-blue-800">
                                <strong>Job ID:</strong> <code class="bg-blue-100 px-2 py-1 rounded">{{ $jobId }}</code>
                            </p>
                            <p class="text-xs text-blue-700 mt-1">
                                Questa pagina si aggiorna automaticamente ogni secondo. Puoi chiuderla e riaprirla in qualsiasi momento.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Progress Display -->
                <div class="my-8" id="progress-display">
                    <div class="mb-4">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-medium text-gray-700" id="progress-message">Caricamento...</span>
                            <span class="text-sm font-medium text-gray-700" id="progress-percentage">0%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div class="bg-blue-600 h-2.5 rounded-full transition-all duration-300" id="progress-bar" style="width: 0%"></div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-6 border-t border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Log Real-time
                    </h2>
                    <div id="log-container" class="bg-gray-900 rounded-lg p-4 h-96 overflow-y-auto font-mono text-xs text-green-400">
                        <div class="space-y-1" id="log-content">
                            <div class="text-gray-500">In attesa di log...</div>
                        </div>
                    </div>
                </div>

                <script>
                    let lastLogCount = 0;

                    // Update progress bar
                    setInterval(() => {
                        fetch('/api/enrich-progress/{{ $jobId }}')
                            .then(r => r.json())
                            .then(data => {
                                if (data) {
                                    const percentage = data.percentage || 0;
                                    const message = data.message || 'Processando...';

                                    document.getElementById('progress-bar').style.width = percentage + '%';
                                    document.getElementById('progress-percentage').textContent = percentage + '%';
                                    document.getElementById('progress-message').textContent = message;

                                    if (data.status === 'completed') {
                                        document.getElementById('progress-bar').classList.remove('bg-blue-600');
                                        document.getElementById('progress-bar').classList.add('bg-green-600');
                                    }
                                }
                            })
                            .catch(e => console.error('Progress fetch error:', e));
                    }, 2000);

                    // Update logs
                    setInterval(() => {
                        fetch('/api/enrich-logs/{{ $jobId }}')
                            .then(r => r.json())
                            .then(data => {
                                if (data.logs && data.logs.length > lastLogCount) {
                                    const container = document.getElementById('log-content');
                                    container.innerHTML = '';
                                    data.logs.forEach(log => {
                                        const div = document.createElement('div');
                                        div.className = 'text-gray-300';
                                        const timestamp = new Date(log.timestamp).toLocaleTimeString('it-IT');
                                        div.innerHTML = `<span class="text-gray-600">[${timestamp}]</span> ${log.message}`;
                                        container.appendChild(div);
                                    });
                                    lastLogCount = data.logs.length;
                                    // Auto scroll to bottom
                                    document.getElementById('log-container').scrollTop = document.getElementById('log-container').scrollHeight;
                                }
                            })
                            .catch(e => console.error('Log fetch error:', e));
                    }, 1000);
                </script>

                <div class="mt-6 pt-6 border-t border-gray-200 text-center">
                    <a href="/admin/components" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                        </svg>
                        Vai ai Componenti
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
