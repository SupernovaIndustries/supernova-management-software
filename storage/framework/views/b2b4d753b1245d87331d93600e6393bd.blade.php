<script>document.addEventListener('DOMContentLoaded', function() {
    // Function to clone pagination controls to top
    function addTopPagination() {
        const tables = document.querySelectorAll('.fi-ta-table-container');
        
        tables.forEach(table => {
            // Find bottom pagination
            const bottomPagination = table.parentNode.querySelector('.fi-ta-pagination');
            
            if (bottomPagination && !table.parentNode.querySelector('.fi-ta-pagination-top-clone')) {
                // Clone the pagination
                const topPagination = bottomPagination.cloneNode(true);
                topPagination.classList.add('fi-ta-pagination-top-clone');
                topPagination.classList.add('border-b', 'border-gray-200', 'dark:border-gray-700', 'pb-4', 'mb-4');
                
                // Add visual indicator
                const indicator = document.createElement('div');
                indicator.className = 'text-sm text-gray-500 dark:text-gray-400 mb-2 flex items-center gap-2';
                indicator.innerHTML = `
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"/>
                    </svg>
                    Navigazione tabella - Controlli duplicati sopra e sotto
                `;
                topPagination.insertBefore(indicator, topPagination.firstChild);
                
                // Insert before table
                table.parentNode.insertBefore(topPagination, table);
                
                // Sync click events between top and bottom pagination
                const topButtons = topPagination.querySelectorAll('button[wire\\:click], a[wire\\:navigate]');
                const bottomButtons = bottomPagination.querySelectorAll('button[wire\\:click], a[wire\\:navigate]');
                
                topButtons.forEach((topBtn, index) => {
                    if (bottomButtons[index]) {
                        topBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            bottomButtons[index].click();
                        });
                    }
                });
            }
        });
    }

    // Function to make headers sticky and improve UX
    function enhanceTableHeaders() {
        const tables = document.querySelectorAll('.fi-ta-table');
        
        tables.forEach(table => {
            // Add scroll indicator
            const container = table.closest('.fi-ta-table-container');
            if (container) {
                container.addEventListener('scroll', function() {
                    const scrollTop = container.scrollTop;
                    const scrollHeight = container.scrollHeight;
                    const clientHeight = container.clientHeight;
                    
                    // Add shadow to sticky header when scrolling
                    const headers = table.querySelectorAll('.fi-ta-header-cell');
                    headers.forEach(header => {
                        if (scrollTop > 0) {
                            header.classList.add('shadow-md');
                        } else {
                            header.classList.remove('shadow-md');
                        }
                    });
                });
            }
        });
    }

    // Function to add quick navigation
    function addQuickNavigation() {
        const tableContainer = document.querySelector('.fi-ta-table-container');
        if (tableContainer) {
            const quickNav = document.createElement('div');
            quickNav.className = 'fixed bottom-4 right-4 z-50 flex flex-col gap-2';
            quickNav.innerHTML = `
                <button onclick="window.scrollTo({top: 0, behavior: 'smooth'})" 
                        class="bg-primary-600 hover:bg-primary-700 text-white p-3 rounded-full shadow-lg transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                    </svg>
                </button>
                <button onclick="window.scrollTo({top: document.body.scrollHeight, behavior: 'smooth'})" 
                        class="bg-gray-600 hover:bg-gray-700 text-white p-3 rounded-full shadow-lg transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                    </svg>
                </button>
            `;
            document.body.appendChild(quickNav);
        }
    }

    // Initialize enhancements
    addTopPagination();
    enhanceTableHeaders();
    addQuickNavigation();

    // Re-run when Livewire updates the table
    document.addEventListener('livewire:navigated', function() {
        setTimeout(() => {
            addTopPagination();
            enhanceTableHeaders();
        }, 100);
    });

    // Also listen for table updates
    if (window.Livewire) {
        Livewire.hook('morph.updated', () => {
            setTimeout(() => {
                addTopPagination();
                enhanceTableHeaders();
            }, 100);
        });
    }
});</script>