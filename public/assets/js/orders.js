// Product-related logic for the Order create/edit form.
// Piece & service handling now lives in order-pieces-services.js.

// Store current product type
var currentProductType = null;

function isMultiProductType(productType) {
    return productType === 'lamix' || productType === 'glass_pkg';
}

// Function to filter products for a specific row
function filterProductsForRow(rowNumber, productType) {
    if (!productType || productType === 'service') {
        return;
    }

    $.ajax({
        url: '/admin/product/get-products-filtered/' + productType,
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val()
        },
        success: function(response) {
            try {
                // Try to access the select using crud.field
                var $select = crud.field('products').subfield('product_id', rowNumber).$input;

                if ($select && $select.length) {
                    var currentVal = $select.val();
                    // Clear all options except the first empty option (if it exists)
                    var firstOption = $select.find('option:first');
                    var isEmptyOption = firstOption.val() === '' || firstOption.val() === null || firstOption.val() === undefined;

                    $select.empty();

                    // Restore the empty option if it existed
                    if (isEmptyOption) {
                        $select.append('<option value="">-</option>');
                    }

                    // Populate with new options from response
                    response.forEach(function(product) {
                        var type = product.product_type || '';
                        var opt = $('<option>')
                            .val(product.id)
                            .text(product.title)
                            .attr('data-product-type', type);
                        $select.append(opt);
                    });

                    // Restore previous selection if still present
                    if (currentVal && $select.find('option[value="' + currentVal + '"]').length) {
                        $select.val(currentVal);
                    }

                    // Trigger change event to update select2 if it's being used
                    $select.trigger('change');
                }
            } catch(e) {
                // Fallback to jQuery if crud.field fails
                var $row = $('[data-repeatable-identifier="products"][data-row-number="' + rowNumber + '"]');
                var $select = $row.find('select[name*="[product_id]"]');
                if ($select.length) {
                    var currentValJq = $select.val();
                    var firstOption = $select.find('option:first');
                    var isEmptyOption = firstOption.val() === '' || firstOption.val() === null || firstOption.val() === undefined;

                    $select.empty();

                    if (isEmptyOption) {
                        $select.append('<option value="">-</option>');
                    }

                response.forEach(function(product) {
                    var type = product.product_type || '';
                    var opt = $('<option>')
                        .val(product.id)
                        .text(product.title)
                        .attr('data-product-type', type);
                    $select.append(opt);
                    });

                    if (currentValJq && $select.find('option[value="' + currentValJq + '"]').length) {
                        $select.val(currentValJq);
                    }

                    $select.trigger('change');
                }
            }
        }
    });
}

crud.field('product_type').onChange(function(field) {
    currentProductType = field.value;

    if (field.value == 'service') {
        crud.field('products').hide();
        // Disable product inputs so the hidden (empty) row is not submitted/validated.
        $('[bp-field-name="products"]').find('input, select').prop('disabled', true);
    } else {
        crud.field('products').show();
        $('[bp-field-name="products"]').find('input, select').prop('disabled', false);
    }

    // Only lamix and glass_pkg allow multiple products
    if (isMultiProductType(field.value)) {
        $('[bp-field-name="products"] button.add-repeatable-element-button').show();
    } else {
        $('[bp-field-name="products"] button.add-repeatable-element-button').hide();
        // Remove extra product rows beyond the first
        var $rows = $('[data-repeatable-identifier="products"][data-row-number]');
        if ($rows.length > 1) {
            $rows.slice(1).remove();
        }
    }

    // Filter all existing rows
    $('[data-repeatable-identifier="products"][data-row-number]').each(function() {
        var rowNumber = parseInt($(this).attr('data-row-number'));
        filterProductsForRow(rowNumber, field.value);
    });
});

// Helper to fetch and set product price for a given row (works for existing and new repeatable rows)
function setProductPriceForRow(rowNumber, productId) {
    if (!productId) return;

    var orderType = crud.field('order_type').$input.val();
    var clientId = crud.field('client_id').$input.val();

    $.ajax({
        url: '/admin/product/get-price/' + productId + '?client_id=' + clientId,
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val()
        },
        success: function(response) {
            var price = orderType === 'wholesale' && response.price_w ? response.price_w : response.price;
            var isCustom = response.is_custom || false;

            var $priceInput = crud.field('products').subfield('price', rowNumber).$input;
            $priceInput.val(price || '');

            // Show/hide custom price badge inside input field
            var $wrapper = $priceInput.closest('[bp-field-wrapper]');
            $wrapper.css('position', 'relative');
            $wrapper.find('.custom-price-badge').remove();

            if (isCustom && price) {
                $priceInput.css('padding-right', '85px');
                var customPriceUrl = '/admin/custom-price';
                var $badge = $('<span class="badge badge-success custom-price-badge" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); font-size: 10px; padding: 3px 6px; cursor: pointer; z-index: 10; background-color: #28a745 !important; color: white;" title="Click to view Custom Prices">CP</span>');
                $badge.on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    window.location.href = customPriceUrl;
                });
                $wrapper.append($badge);
            } else {
                $priceInput.css('padding-right', '');
            }
        },
        error: function() {
            console.error('Failed to fetch product price');
        }
    });
}

function refreshProductPricesForAllRows() {
    $('[data-repeatable-identifier="products"][data-row-number]').each(function() {
        var rowNumber = parseInt($(this).attr('data-row-number'));
        var productId = null;
        try {
            productId = crud.field('products').subfield('product_id', rowNumber).$input.val();
        } catch (e) {
            productId = $(this).find('select[name*="[product_id]"]').val();
        }
        if (productId) {
            setProductPriceForRow(rowNumber, productId);
        }
    });
}

crud.field('client_id').onChange(function() {
    refreshProductPricesForAllRows();
});

crud.field('order_type').onChange(function() {
    refreshProductPricesForAllRows();
});

crud.field('products').subfield('product_id').onChange(function(field) {
    // Remove any existing custom price badge when product changes
    var $priceInput = crud.field('products').subfield('price', field.rowNumber).$input;
    var $wrapper = $priceInput.closest('[bp-field-wrapper]');
    $wrapper.find('.custom-price-badge').remove();
    $priceInput.css('padding-right', '');

    setProductPriceForRow(field.rowNumber, field.value);
});

$(document).ready(function() {
    // Store initial product type
    try {
        currentProductType = crud.field('product_type').$input.val();
    } catch(e) {}

    // Apply the service-mode product state on load (the onChange handler only fires on change,
    // so editing an existing service-only order would otherwise leave products enabled).
    if (currentProductType === 'service') {
        try { crud.field('products').hide(); } catch(e) {}
        $('[bp-field-name="products"]').find('input, select').prop('disabled', true);
    }

    // Check if we're on edit page
    var isEditPage = window.location.pathname.includes('/edit');

    // On create page, ensure product_id fields start with no selection
    if (!isEditPage) {
        setTimeout(function() {
            $('[data-repeatable-identifier="products"][data-row-number]').each(function() {
                var $row = $(this);
                var rowNumber = parseInt($row.attr('data-row-number'));
                try {
                    var $select = crud.field('products').subfield('product_id', rowNumber).$input;
                    if ($select && $select.length && $select.val()) {
                        $select.val(null).trigger('change');
                    }
                } catch(e) {
                    // Fallback to jQuery
                    var $select = $row.find('select[name*="[product_id]"]');
                    if ($select.length && $select.val()) {
                        $select.val(null).trigger('change');
                    }
                }
            });
        }, 100);
    }

    // On edit page, refresh product options to ensure data-product-type is present and selection kept
    (function refreshProductsOnLoad() {
        if (!currentProductType) return;
        $('[data-repeatable-identifier="products"][data-row-number]').each(function() {
            var rowNumber = parseInt($(this).attr('data-row-number'));
            filterProductsForRow(rowNumber, currentProductType);
        });
    })();

    // Remove custom price badge when price is manually changed
    $(document).on('input change', '[data-repeatable-identifier="products"] input[name*="[price]"]', function() {
        var $input = $(this);
        var $wrapper = $input.closest('[bp-field-wrapper]');
        $wrapper.find('.custom-price-badge').remove();
        $input.css('padding-right', '');
    });

    // Listen for clicks on add button and filter after row is added
    $(document).on('click', '[bp-field-name="products"] button.add-repeatable-element-button', function() {
        if (!isMultiProductType(currentProductType)) {
            // prevent adding when not allowed
            return false;
        }
        setTimeout(function() {
            if (currentProductType) {
                // Find the newly added row (highest row number)
                var maxRowNumber = 0;
                $('[data-repeatable-identifier="products"][data-row-number]').each(function() {
                    var rowNum = parseInt($(this).attr('data-row-number'));
                    if (rowNum > maxRowNumber) {
                        maxRowNumber = rowNum;
                    }
                });
                if (maxRowNumber > 0) {
                    filterProductsForRow(maxRowNumber, currentProductType);
                }
            }
        }, 300);
    });

    // Use MutationObserver to detect when new rows are added to products repeatable field
    var productsContainer = document.querySelector('[bp-field-name="products"]');
    if (productsContainer) {
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1 && node.hasAttribute && node.hasAttribute('data-repeatable-identifier') &&
                        node.getAttribute('data-repeatable-identifier') === 'products') {
                        // If current type does not allow multiple, remove extra nodes
                        var $rows = $('[data-repeatable-identifier="products"][data-row-number]');
                        if (!isMultiProductType(currentProductType) && $rows.length > 1) {
                            // remove the newly added node
                            $(node).remove();
                            return;
                        }

                        var rowNumber = parseInt(node.getAttribute('data-row-number'));
                        if (rowNumber && currentProductType) {
                            setTimeout(function() {
                                filterProductsForRow(rowNumber, currentProductType);
                            }, 200);
                        }
                    }
                });
            });
        });

        observer.observe(productsContainer, {
            childList: true,
            subtree: true
        });
    }
});
