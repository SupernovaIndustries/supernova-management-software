<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Informazioni Aziendali Always Visible -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <div class="mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                    📋 Informazioni Aziendali (Sempre Visibili)
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Dati aziendali per comunicazioni e acquisti online
                </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div class="space-y-2">
                    <div class="flex">
                        <span class="font-medium text-gray-700 dark:text-gray-300 w-24">Nome:</span>
                        <span class="text-gray-900 dark:text-white">{{ $profile->owner_name }}</span>
                    </div>
                    <div class="flex">
                        <span class="font-medium text-gray-700 dark:text-gray-300 w-24">Ruolo:</span>
                        <span class="text-gray-900 dark:text-white">{{ $profile->owner_title }}</span>
                    </div>
                    <div class="flex">
                        <span class="font-medium text-gray-700 dark:text-gray-300 w-24">Azienda:</span>
                        <span class="text-gray-900 dark:text-white">{{ $profile->company_name }}</span>
                    </div>
                    <div class="flex">
                        <span class="font-medium text-gray-700 dark:text-gray-300 w-24">P.IVA:</span>
                        <span class="text-gray-900 dark:text-white">{{ $profile->vat_number }}</span>
                    </div>
                </div>
                
                <div class="space-y-2">
                    <div class="flex">
                        <span class="font-medium text-gray-700 dark:text-gray-300 w-24">C.F.:</span>
                        <span class="text-gray-900 dark:text-white">{{ $profile->tax_code }}</span>
                    </div>
                    <div class="flex">
                        <span class="font-medium text-gray-700 dark:text-gray-300 w-24">SDI:</span>
                        <span class="text-gray-900 dark:text-white">{{ $profile->sdi_code }}</span>
                    </div>
                    <div class="flex">
                        <span class="font-medium text-gray-700 dark:text-gray-300 w-24">Sede:</span>
                        <span class="text-gray-900 dark:text-white">{{ $profile->formatted_address }}</span>
                    </div>
                    @if($profile->email)
                    <div class="flex">
                        <span class="font-medium text-gray-700 dark:text-gray-300 w-24">Email:</span>
                        <span class="text-gray-900 dark:text-white">{{ $profile->email }}</span>
                    </div>
                    @endif
                </div>
            </div>
            
            <!-- Quick Copy Actions -->
            <div class="mt-4 flex flex-wrap gap-2">
                <button 
                    onclick="copyToClipboard('{{ $profile->company_name }}\nC.F/P.IVA {{ $profile->vat_number }}\nSDI {{ $profile->sdi_code }}\n{{ $profile->formatted_address }}')"
                    class="px-3 py-1 bg-blue-100 text-blue-800 rounded text-xs hover:bg-blue-200 transition-colors"
                >
                    📋 Copia Dati Completi
                </button>
                <button 
                    onclick="copyToClipboard('{{ $profile->vat_number }}')"
                    class="px-3 py-1 bg-green-100 text-green-800 rounded text-xs hover:bg-green-200 transition-colors"
                >
                    📋 Copia P.IVA
                </button>
                <button 
                    onclick="copyToClipboard('{{ $profile->sdi_code }}')"
                    class="px-3 py-1 bg-purple-100 text-purple-800 rounded text-xs hover:bg-purple-200 transition-colors"
                >
                    📋 Copia SDI
                </button>
            </div>
        </div>

        <!-- Status Indicators -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Claude AI Status -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        @if($profile->isClaudeEnabled())
                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                <span class="text-green-600 text-sm">🤖</span>
                            </div>
                        @else
                            <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center">
                                <span class="text-gray-400 text-sm">🤖</span>
                            </div>
                        @endif
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">Claude AI</p>
                        <p class="text-xs text-gray-500">
                            @if($profile->isClaudeEnabled())
                                <span class="text-green-600">Configurato</span>
                            @else
                                <span class="text-gray-400">Non configurato</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            <!-- Email Status -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        @if($profile->isEmailConfigured())
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                <span class="text-blue-600 text-sm">📧</span>
                            </div>
                        @else
                            <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center">
                                <span class="text-gray-400 text-sm">📧</span>
                            </div>
                        @endif
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">Email SMTP</p>
                        <p class="text-xs text-gray-500">
                            @if($profile->isEmailConfigured())
                                <span class="text-blue-600">Configurato</span>
                            @else
                                <span class="text-gray-400">Non configurato</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            <!-- Documents Status -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        @if($profile->logo_path)
                            <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                                <span class="text-yellow-600 text-sm">📄</span>
                            </div>
                        @else
                            <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center">
                                <span class="text-gray-400 text-sm">📄</span>
                            </div>
                        @endif
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">Documenti</p>
                        <p class="text-xs text-gray-500">
                            @if($profile->logo_path)
                                <span class="text-yellow-600">Logo caricato</span>
                            @else
                                <span class="text-gray-400">Nessun documento</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <form wire:submit="save">
                {{ $this->form }}
                
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex justify-between">
                        <div class="flex space-x-2">
                            @foreach($this->getFormActions() as $action)
                                {{ $action }}
                            @endforeach
                        </div>
                        <div class="text-xs text-gray-500">
                            Ultimo aggiornamento: {{ $profile->updated_at?->format('d/m/Y H:i') ?? 'Mai' }}
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            // Success feedback
            const toast = document.createElement('div');
            toast.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded shadow-lg z-50';
            toast.textContent = 'Copiato negli appunti!';
            document.body.appendChild(toast);
            
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 2000);
        }).catch(function(err) {
            console.error('Could not copy text: ', err);
        });
    }
    </script>
</x-filament-panels::page>