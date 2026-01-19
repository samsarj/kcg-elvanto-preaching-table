/**
 * Preaching Table JavaScript
 * Client-side functionality for the KCG Elvanto Preaching Table plugin
 */

(function() {
    'use strict';
    
    /**
     * Initialize the preaching table
     */
    function initPreachingTable() {
        // Add sorting functionality to table headers
        document.querySelectorAll('.kcg-preaching-table thead th').forEach(function(th) {
            th.style.cursor = 'pointer';
            th.addEventListener('click', function() {
                handleTableSort(th);
            });
        });
        
        // Add alternating row colors
        document.querySelectorAll('.kcg-preaching-table tbody tr').forEach(function(tr, index) {
            if (index % 2 === 0) {
                tr.classList.add('even-row');
            } else {
                tr.classList.add('odd-row');
            }
        });
    }
    
    /**
     * Handle table sorting
     */
    function handleTableSort(headerElement) {
        const table = headerElement.closest('table');
        if (!table) return;
        
        const headerIndex = Array.from(headerElement.parentNode.children).indexOf(headerElement);
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        // Determine sort direction
        const isAscending = !headerElement.classList.contains('sort-asc');
        
        // Remove sort classes from all headers
        table.querySelectorAll('thead th').forEach(th => {
            th.classList.remove('sort-asc', 'sort-desc');
        });
        
        // Add sort class to current header
        headerElement.classList.add(isAscending ? 'sort-asc' : 'sort-desc');
        
        // Sort rows
        rows.sort(function(a, b) {
            const cellA = a.children[headerIndex].textContent.trim();
            const cellB = b.children[headerIndex].textContent.trim();
            
            // Try to parse as date if it's the date column
            if (headerIndex === 0) {
                const dateA = new Date(cellA);
                const dateB = new Date(cellB);
                
                if (!isNaN(dateA.getTime()) && !isNaN(dateB.getTime())) {
                    return isAscending ? dateA - dateB : dateB - dateA;
                }
            }
            
            // Try to parse as number
            const numA = parseFloat(cellA.replace(/[^0-9.-]/g, ''));
            const numB = parseFloat(cellB.replace(/[^0-9.-]/g, ''));
            
            if (!isNaN(numA) && !isNaN(numB)) {
                return isAscending ? numA - numB : numB - numA;
            }
            
            // Fall back to string comparison
            return isAscending ? 
                cellA.localeCompare(cellB) : 
                cellB.localeCompare(cellA);
        });
        
        // Reorder rows in DOM
        rows.forEach(row => tbody.appendChild(row));
    }
    
    /**
     * Load preaching services via REST API
     */
    function loadPreachingServices(callback) {
        if (typeof wpApiSettings === 'undefined') {
            console.warn('WordPress REST API not available');
            return;
        }
        
        const url = wpApiSettings.root + 'kcg-elvanto/v1/preaching-services';
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (typeof callback === 'function') {
                    callback(data);
                }
            })
            .catch(error => {
                console.error('Error loading preaching services:', error);
            });
    }
    
    /**
     * Add table sorting styles dynamically
     */
    function addSortingStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .kcg-preaching-table thead th.sort-asc::after {
                content: ' ↑';
                font-size: 0.8em;
            }
            
            .kcg-preaching-table thead th.sort-desc::after {
                content: ' ↓';
                font-size: 0.8em;
            }
            
            .kcg-preaching-table tbody tr.even-row {
                background-color: #fafafa;
            }
        `;
        document.head.appendChild(style);
    }
    
    /**
     * Initialize on DOM ready
     */
    function onDOMReady(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }
    
    // Initialize when DOM is ready
    onDOMReady(function() {
        addSortingStyles();
        initPreachingTable();
    });
    
    // Export functions for external use
    window.kcgPreachingTable = {
        loadServices: loadPreachingServices,
        init: initPreachingTable
    };
})();
