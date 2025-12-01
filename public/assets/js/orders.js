// Store current product type
var currentProductType = null;

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
            console.log(response);
            try {
                // Try to access the select using crud.field
                var $select = crud.field('products').subfield('product_id', rowNumber).$input;
                
                if ($select && $select.length) {
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
                        $select.append('<option value="' + product.id + '">' + product.title + '</option>');
                    });
                    
                    // Trigger change event to update select2 if it's being used
                    $select.trigger('change');
                }
            } catch(e) {
                // Fallback to jQuery if crud.field fails
                var $row = $('[data-repeatable-identifier="products"][data-row-number="' + rowNumber + '"]');
                var $select = $row.find('select[name*="[product_id]"]');
                if ($select.length) {
                    var firstOption = $select.find('option:first');
                    var isEmptyOption = firstOption.val() === '' || firstOption.val() === null || firstOption.val() === undefined;
                    
                    $select.empty();
                    
                    if (isEmptyOption) {
                        $select.append('<option value="">-</option>');
                    }
                    
                    response.forEach(function(product) {
                        $select.append('<option value="' + product.id + '">' + product.title + '</option>');
                    });
                    
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
        crud.field('pieces').hide();
    } else {
        crud.field('products').show();
        crud.field('pieces').show();
    }
 
    if (field.value == 'mirror' || field.value == 'glass') {
        $('[bp-field-name="products"] button.add-repeatable-element-button').hide();
    } else {
        $('[bp-field-name="products"] button.add-repeatable-element-button').show();
    }

    // Filter all existing rows
    $('[data-repeatable-identifier="products"][data-row-number]').each(function() {
        var rowNumber = parseInt($(this).attr('data-row-number'));
        filterProductsForRow(rowNumber, field.value);
    });
});


crud.field('services').subfield('service_id').onChange(function(field) {
    // Hide all subfields in the current row first
    hideAllSubfields(field.rowNumber);
    
    // Then show only the relevant fields for the selected service
    if (field.value) {
        showExtraFields(field.value, field.rowNumber);
    }
});

function showExtraFields(serviceId, rowNumber) {
    $.ajax({
        url: '/admin/service/get-extra-fields/' + serviceId,
        method: 'GET',
        success: function(response) {
            for (var i = 0; i < response.extra_field_names.length; i++) {
                crud.field('services').subfield(response.extra_field_names[i], rowNumber).show();
            }
        }
    });
}

function hideAllSubfields(rowNumber) {
    var $row = $('[data-repeatable-identifier="services"][data-row-number="' + rowNumber + '"]');
    $row.find('[bp-field-name]').each(function() {
        var name = $(this).attr('bp-field-name').split('.').pop();
        if (name !== 'service_id' && name !== 'calculate_price_btn') {
            try { crud.field('services').subfield(name, rowNumber).hide(); } catch(e) {}
        }
    });
}


// Calculate price for a specific row
function calculateRowPrice(rowNumber) {
    var $row = $('[data-repeatable-identifier="services"][data-row-number="' + rowNumber + '"]');
    var formData = {};
    
    // Only get values from visible fields
    $row.find('[bp-field-wrapper]:visible').each(function() {
        var $wrapper = $(this);
        var $input = $wrapper.find('input, select, textarea').first();
        if ($input.length) {
            var name = $input.attr('name') || $input.attr('data-repeatable-input-name');
            if (name) {
                var match = name.match(/\[([^\]]+)\]$/);
                if (match) formData[match[1]] = $input.val();
            }
        }
    });
    
    if (!formData.service_id) {
        alert('Please select a service first');
        return;
    }
    
    $.ajax({
        url: '/admin/order/calculate-service-price',
        method: 'POST',
        data: formData,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val()
        },
        success: function(response) {
            if (response.price_gel !== undefined) {
                crud.field('services').subfield('price_gel', rowNumber).$input.val(response.price_gel);
            }
        }
    });
}

// Initialize subfields on page load (for edit page)
$(document).ready(function() {
    // Store initial product type
    try {
        currentProductType = crud.field('product_type').$input.val();
    } catch(e) {}
    
    setTimeout(function() {
        $('[data-repeatable-identifier="services"][data-row-number]').each(function() {
            var rowNumber = parseInt($(this).attr('data-row-number'));
            var serviceId = crud.field('services').subfield('service_id', rowNumber).$input.val();
            if (serviceId) {
                hideAllSubfields(rowNumber);
                showExtraFields(serviceId, rowNumber);
            }
        });
    }, 500);
    
    // Handle calculate price button clicks
    $(document).on('click', '.calculate-price-btn', function() {
        var $row = $(this).closest('[data-repeatable-identifier="services"][data-row-number]');
        var rowNumber = parseInt($row.attr('data-row-number'));
        calculateRowPrice(rowNumber);
    });
    
    // Listen for clicks on add button and filter after row is added
    $(document).on('click', '[bp-field-name="products"] button.add-repeatable-element-button', function() {
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



