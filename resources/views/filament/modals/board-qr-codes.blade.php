<div class="p-4">
    {{-- Header with info --}}
    <div class="mb-6 p-4 bg-gray-100 dark:bg-gray-800 rounded-lg">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <span class="font-semibold text-gray-700 dark:text-gray-300">Progetto:</span>
                <p class="text-gray-900 dark:text-white">{{ $assemblyLog->project->code }}</p>
            </div>
            <div>
                <span class="font-semibold text-gray-700 dark:text-gray-300">Batch:</span>
                <p class="text-gray-900 dark:text-white">{{ $assemblyLog->batch_number }}</p>
            </div>
            <div>
                <span class="font-semibold text-gray-700 dark:text-gray-300">Data:</span>
                <p class="text-gray-900 dark:text-white">{{ $assemblyLog->assembly_date->format('d/m/Y') }}</p>
            </div>
            <div>
                <span class="font-semibold text-gray-700 dark:text-gray-300">Schede:</span>
                <p class="text-gray-900 dark:text-white">{{ $assemblyLog->boards_count }}</p>
            </div>
        </div>
    </div>

    {{-- Instructions --}}
    <div class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
        <p class="text-sm text-blue-800 dark:text-blue-200">
            <strong>Istruzioni:</strong> Clicca su un QR code per scaricarlo. I QR code sono salvati su Nextcloud in:
            <code class="text-xs bg-white dark:bg-gray-800 px-1 py-0.5 rounded">03_Produzione/QR_Codes/</code>
        </p>
    </div>

    {{-- QR Codes Grid --}}
    @if($qrCodes->isNotEmpty())
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            @foreach($qrCodes as $qrCode)
                <div class="border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-800 hover:shadow-lg transition-shadow">
                    {{-- Board Number Badge --}}
                    <div class="mb-2 flex justify-between items-center">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                            Scheda #{{ $qrCode->board_number }}
                        </span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $qrCode->generated_at?->format('d/m H:i') }}
                        </span>
                    </div>

                    {{-- QR Code Image --}}
                    <div class="bg-white p-2 rounded-lg mb-3 flex items-center justify-center" style="min-height: 200px;">
                        @php
                            $qrCodeService = new \App\Services\BoardQrCodeService();
                            $base64Image = $qrCodeService->getQrCodeAsBase64($qrCode);
                        @endphp

                        @if($base64Image)
                            <img src="{{ $base64Image }}"
                                 alt="QR Code #{{ $qrCode->board_number }}"
                                 class="w-full h-auto max-w-[200px]"
                                 style="image-rendering: pixelated;">
                        @else
                            <div class="text-center text-gray-500">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <p class="mt-2 text-xs">Impossibile caricare</p>
                            </div>
                        @endif
                    </div>

                    {{-- QR Data --}}
                    <div class="mb-3">
                        <p class="text-xs font-mono text-gray-600 dark:text-gray-400 break-all bg-gray-100 dark:bg-gray-700 p-2 rounded">
                            {{ $qrCode->qr_data }}
                        </p>
                    </div>

                    {{-- Download Button --}}
                    <a href="{{ route('board-qr-code.download', $qrCode) }}"
                       target="_blank"
                       class="w-full inline-flex justify-center items-center px-3 py-2 border border-transparent text-xs font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        Scarica PNG
                    </a>
                </div>
            @endforeach
        </div>

        {{-- Print All Button --}}
        <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button onclick="window.print()"
                    class="w-full inline-flex justify-center items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                </svg>
                Stampa tutti i QR Code
            </button>
        </div>

    @else
        {{-- Empty State --}}
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Nessun QR Code</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">I QR code verranno generati automaticamente.</p>
        </div>
    @endif
</div>

{{-- Print Styles --}}
<style>
    @media print {
        /* Hide non-QR elements when printing */
        .fi-modal-header,
        .fi-modal-footer,
        button {
            display: none !important;
        }

        /* Optimize QR code grid for printing */
        .grid {
            display: grid !important;
            grid-template-columns: repeat(4, 1fr) !important;
            gap: 1rem !important;
            page-break-inside: avoid;
        }

        /* Each QR code in its own cell */
        .grid > div {
            page-break-inside: avoid;
            border: 1px solid #000 !important;
            background: white !important;
        }

        /* Ensure QR images print well */
        img {
            image-rendering: pixelated;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>
