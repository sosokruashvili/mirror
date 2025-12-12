/**
 * Payment Filters Page Reload Handler
 * 
 * This script intercepts filter changes and forces a page reload
 * instead of using AJAX, so widgets can read updated URL parameters.
 */

(function() {
    'use strict';
    
    // Wait for DOM and crud to be ready
    jQuery(document).ready(function($) {
        // Wait a bit for crud object to be initialized
        setTimeout(function() {
            // Override the filter change function to reload the page
            if (typeof window.updateDatatablesOnFilterChange === 'function') {
                var originalUpdateDatatablesOnFilterChange = window.updateDatatablesOnFilterChange;
                
                window.updateDatatablesOnFilterChange = function(filterName, filterValue, update_url = false, debounce = 500) {
                    // Build the new URL with the filter parameter
                    var current_url = typeof crud !== 'undefined' && typeof crud.table !== 'undefined' 
                        ? crud.table.ajax.url() 
                        : window.location.href;
                    
                    var new_url = addOrUpdateUriParameter(current_url, filterName, filterValue);
                    new_url = normalizeAmpersand(new_url);
                    
                    // Extract the base URL and query parameters
                    var urlParts = new_url.split('?');
                    var baseUrl = urlParts[0].replace('/search', ''); // Remove /search from DataTable URL
                    var queryString = urlParts.length > 1 ? urlParts[1] : '';
                    
                    // Build the final URL for page reload
                    var finalUrl = baseUrl + (queryString ? '?' + queryString : '');
                    
                    // Reload the page with the new URL
                    window.location.href = finalUrl;
                    
                    return new_url;
                };
            }
            
            // Override the remove filters button click
            $(document).off('click', '#remove_filters_button').on('click', '#remove_filters_button', function(e) {
                e.preventDefault();
                
                // Get the base route URL from current location
                var currentPath = window.location.pathname;
                var baseUrl = currentPath.split('?')[0];
                
                // Clear all filters and reload
                window.location.href = baseUrl;
            });
        }, 100);
    });
})();

