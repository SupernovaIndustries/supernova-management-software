<style>/* Table Header Sticky */
.fi-ta-header-cell {
    position: sticky !important;
    top: 0 !important;
    z-index: 10 !important;
    /* Use default theme colors - no forced backgrounds */
}

/* Table controls sticky (search, filters, actions) */
.fi-ta-header-toolbar {
    position: sticky !important;
    top: 0 !important;
    z-index: 20 !important;
    /* Use default theme background - no forced colors */
    padding-bottom: 1rem !important;
    margin-bottom: 0 !important;
}

/* Pagination top */
.fi-ta-pagination-top {
    position: sticky !important;
    top: 80px !important; /* Adjust based on header height */
    z-index: 15 !important;
    /* Use default theme colors */
    padding: 0.75rem 0 !important;
}

/* Add top pagination if it doesn't exist */
.fi-ta-table-container::before {
    content: '';
    display: block;
    position: sticky;
    top: 60px;
    height: 1px;
    background: transparent;
    z-index: 5;
}

/* Enhance table scrolling */
.fi-ta-table-container {
    overflow-y: auto !important;
    max-height: calc(100vh - 200px) !important;
}

/* Smooth scrolling */
html {
    scroll-behavior: smooth;
}

/* Better table cell spacing when sticky */
.fi-ta-cell {
    border-bottom: 1px solid rgb(243 244 246) !important; /* gray-100 */
}

.dark .fi-ta-cell {
    border-bottom: 1px solid rgb(31 41 55) !important; /* gray-800 */
}

/* Fix z-index conflicts with dropdowns */
.fi-dropdown-panel {
    z-index: 50 !important;
}

.fi-modal {
    z-index: 100 !important;
}

/* Table loading state */
.fi-ta-table.fi-ta-table--loading {
    opacity: 0.7;
}

/* Better mobile responsive for sticky headers */
@media (max-width: 768px) {
    .fi-ta-header-toolbar {
        position: relative !important;
        top: auto !important;
    }
    
    .fi-ta-header-cell {
        position: relative !important;
        top: auto !important;
    }
    
    .fi-ta-table-container {
        max-height: none !important;
    }
}</style><?php /**PATH /Users/supernova/supernova-management/storage/framework/views/7aee8570399ce745d3e6460836b0fc0c.blade.php ENDPATH**/ ?>