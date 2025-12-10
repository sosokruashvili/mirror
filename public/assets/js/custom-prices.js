// Auto-fill price_usd when product is selected
$(document).ready(function() {
    // Use Backpack's onChange if available, otherwise use jQuery change event
    if (typeof crud !== 'undefined' && typeof crud.field === 'function') {
        try {
            crud.field('product_id').onChange(function(field) {
                if (field.value) {
                    fetchProductPrice(field.value);
                }
            });
        } catch (e) {
            console.log('Using jQuery fallback for product_id onChange');
            // Fallback to jQuery if Backpack API fails
            $(document).on('change', 'select[name="product_id"]', function() {
                var productId = $(this).val();
                if (productId) {
                    fetchProductPrice(productId);
                }
            });
        }
    } else {
        // Fallback if crud is not available
        $(document).on('change', 'select[name="product_id"]', function() {
            var productId = $(this).val();
            if (productId) {
                fetchProductPrice(productId);
            }
        });
    }
    
    // Auto-fill price on page load if product is already selected and price_usd is empty (for create page only)
    // Check if we're on create page (no ID in URL) vs edit page
    var isCreatePage = window.location.pathname.indexOf('/create') !== -1 || 
                       (window.location.pathname.indexOf('/edit') === -1 && window.location.pathname.indexOf('/update') === -1);
    
    if (isCreatePage) {
        setTimeout(function() {
            try {
                var productId = crud.field('product_id').$input.val();
                var priceUsd = crud.field('price_usd').$input.val();
                
                // Only auto-fill if product is selected but price is empty
                if (productId && !priceUsd) {
                    fetchProductPrice(productId);
                }
            } catch (e) {
                // Fallback to jQuery
                var $productSelect = $('select[name="product_id"]');
                var $priceInput = $('input[name="price_usd"]');
                
                if ($productSelect.length && $priceInput.length) {
                    var productId = $productSelect.val();
                    var priceUsd = $priceInput.val();
                    
                    // Only auto-fill if product is selected but price is empty
                    if (productId && !priceUsd) {
                        fetchProductPrice(productId);
                    }
                }
            }
        }, 500);
    }
});

function fetchProductPrice(productId) {
    if (!productId) return;

    $.ajax({
        url: '/admin/product/get-price/' + productId,
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val()
        },
        success: function(response) {
            // Use the default product price (retail price)
            var price = response.price || '';

            // Try to use Backpack API first
            try {
                crud.field('price_usd').$input.val(price);
                return;
            } catch (e) {
                // Fallback to direct DOM selection
                var $priceInput = $('input[name="price_usd"]');
                if ($priceInput.length) {
                    $priceInput.val(price);
                }
            }
        },
        error: function() {
            console.error('Failed to fetch product price');
        }
    });
}

