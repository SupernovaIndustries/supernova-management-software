<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Import Componenti</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 antialiased">
    <div class="min-h-screen py-8">
        <div class="container mx-auto px-4">
            <div class="max-w-4xl mx-auto">
                <div class="flex items-center mb-6">
                    <a href="/admin/components" class="text-blue-600 hover:text-blue-800 mr-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </a>
                    <h1 class="text-3xl font-bold text-gray-900">📦 Monitor Import Componenti</h1>
                </div>

                <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        <div class="flex-1">
                            <p class="text-sm text-blue-800">
                                <strong>Dashboard Import</strong>
                            </p>
                            <p class="text-xs text-blue-700 mt-1">
                                Questa pagina si aggiorna automaticamente ogni 2 secondi. Mostra tutti i job di import in esecuzione e completati.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Jobs List -->
                <div id="jobs-container" class="space-y-4">
                    <div class="text-gray-500 text-center py-8">Caricamento job...</div>
                </div>

                <script>
                    function updateJobs() {
                        fetch('/api/import-jobs')
                            .then(r => r.json())
                            .then(data => {
                                const container = document.getElementById('jobs-container');

                                if (!data.jobs || data.jobs.length === 0) {
                                    container.innerHTML = `
                                        <div class="bg-white rounded-lg shadow-lg p-8 text-center">
                                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            <h3 class="mt-2 text-sm font-medium text-gray-900">Nessun import trovato</h3>
                                            <p class="mt-1 text-sm text-gray-500">Inizia un nuovo import dalla pagina componenti</p>
                                        </div>
                                    `;
                                    return;
                                }

                                container.innerHTML = data.jobs.map(job => {
                                    let statusBadge = '';
                                    let progressSection = '';
                                    let resultSection = '';
                                    let actionButton = '';

                                    if (job.status === 'processing') {
                                        statusBadge = `
                                            <div class="flex items-center gap-2">
                                                <div class="animate-spin h-5 w-5 border-2 border-blue-600 border-t-transparent rounded-full"></div>
                                                <h3 class="text-lg font-semibold text-gray-900">Import in corso...</h3>
                                            </div>
                                        `;
                                        progressSection = `
                                            <div class="mt-4">
                                                <div class="flex justify-between text-sm mb-2">
                                                    <span class="text-gray-700">${job.message || 'Elaborazione...'}</span>
                                                    <span class="font-semibold text-blue-600">${job.percentage || 0}%</span>
                                                </div>
                                                <div class="w-full bg-gray-200 rounded-full h-3">
                                                    <div class="bg-blue-600 h-3 rounded-full transition-all duration-300" style="width: ${job.percentage || 0}%"></div>
                                                </div>
                                                <div class="mt-2 text-sm text-gray-600">
                                                    <span class="font-medium">${job.current || 0}</span> / <span class="font-medium">${job.total || 0}</span> componenti
                                                </div>
                                            </div>
                                        `;
                                        actionButton = `
                                            <a href="/admin/import-progress/${job.job_id}" target="_blank"
                                               class="text-xs text-blue-600 hover:text-blue-700 font-medium inline-flex items-center gap-1 mt-3">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                                Vedi Log Real-time
                                            </a>
                                        `;
                                    } else if (job.status === 'completed') {
                                        statusBadge = `
                                            <div class="flex items-center gap-2">
                                                <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <h3 class="text-lg font-semibold text-green-700">Import completato</h3>
                                            </div>
                                        `;
                                        resultSection = `
                                            <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4">
                                                <div class="bg-green-50 rounded-lg p-3">
                                                    <div class="text-2xl font-bold text-green-700">${job.result?.imported || 0}</div>
                                                    <div class="text-xs text-green-600">Importati</div>
                                                </div>
                                                <div class="bg-blue-50 rounded-lg p-3">
                                                    <div class="text-2xl font-bold text-blue-700">${job.result?.updated || 0}</div>
                                                    <div class="text-xs text-blue-600">Aggiornati</div>
                                                </div>
                                                <div class="bg-yellow-50 rounded-lg p-3">
                                                    <div class="text-2xl font-bold text-yellow-700">${job.result?.skipped || 0}</div>
                                                    <div class="text-xs text-yellow-600">Saltati</div>
                                                </div>
                                                <div class="bg-gray-50 rounded-lg p-3">
                                                    <div class="text-2xl font-bold text-gray-700">${(job.result?.imported || 0) + (job.result?.updated || 0) + (job.result?.skipped || 0)}</div>
                                                    <div class="text-xs text-gray-600">Totale</div>
                                                </div>
                                            </div>
                                        `;
                                        actionButton = `
                                            <div class="mt-3 flex items-center justify-between">
                                                <span class="text-xs text-gray-500">Completato ${new Date(job.completed_at).toLocaleString('it-IT')}</span>
                                                <a href="/admin/import-progress/${job.job_id}" target="_blank"
                                                   class="text-xs text-blue-600 hover:text-blue-700 font-medium inline-flex items-center gap-1">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                    </svg>
                                                    Vedi Log Dettagliati
                                                </a>
                                            </div>
                                        `;
                                    } else if (job.status === 'failed') {
                                        statusBadge = `
                                            <div class="flex items-center gap-2">
                                                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <h3 class="text-lg font-semibold text-red-700">Import fallito</h3>
                                            </div>
                                        `;
                                        resultSection = `
                                            <div class="mt-4 bg-red-50 border border-red-200 rounded-lg p-4">
                                                <p class="text-sm text-red-800 font-mono">${job.error || 'Errore sconosciuto'}</p>
                                            </div>
                                        `;
                                        actionButton = `
                                            <div class="mt-3 flex items-center justify-between">
                                                <span class="text-xs text-gray-500">Fallito ${new Date(job.failed_at).toLocaleString('it-IT')}</span>
                                                <a href="/admin/import-progress/${job.job_id}" target="_blank"
                                                   class="text-xs text-blue-600 hover:text-blue-700 font-medium inline-flex items-center gap-1">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                    </svg>
                                                    Vedi Log Dettagliati
                                                </a>
                                            </div>
                                        `;
                                    }

                                    return `
                                        <div class="bg-white rounded-lg shadow-lg p-6 border border-gray-200">
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1">
                                                    ${statusBadge}
                                                    <span class="text-xs text-gray-500 font-mono mt-1 inline-block">${job.job_id}</span>
                                                    ${progressSection}
                                                    ${resultSection}
                                                    ${actionButton}
                                                </div>
                                            </div>
                                        </div>
                                    `;
                                }).join('');
                            })
                            .catch(e => console.error('Jobs fetch error:', e));
                    }

                    // Initial load
                    updateJobs();

                    // Auto-refresh every 2 seconds
                    setInterval(updateJobs, 2000);
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
