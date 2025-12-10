// Store current product type
var currentProductType = null;

function isMultiProductType(productType) {
    return productType === 'lamix' || productType === 'glass_pkg';
}

// Flag to track programmatic updates (to ignore them in change handlers)
var isProgrammaticPieceUpdate = false;

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
        crud.field('pieces').hide();
    } else {
        crud.field('products').show();
        crud.field('pieces').show();
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

    // Get order type to determine if we should use retail or wholesale price
    var orderType = '';
    try {
        orderType = crud.field('order_type').$input.val();
    } catch (e) {
        // Fallback to jQuery
        orderType = $('select[name="order_type"], input[name="order_type"]').val() || 'retail';
    }

    $.ajax({
        url: '/admin/product/get-price/' + productId,
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val()
        },
        success: function(response) {
            var price = orderType === 'wholesale' && response.price_w ? response.price_w : response.price;

            // Prefer Backpack API if available
            try {
                crud.field('products').subfield('price', rowNumber).$input.val(price || '');
                return;
            } catch (e) {
                // Fallback to direct DOM selection
                var $row = $('[data-repeatable-identifier="products"][data-row-number="' + rowNumber + '"]');
                var $priceInput = $row.find('input[name*="[price]"]');
                if ($priceInput.length) {
                    $priceInput.val(price || '');
                }
            }
        },
        error: function() {
            console.error('Failed to fetch product price');
        }
    });
}

crud.field('products').subfield('product_id').onChange(function(field) {
    setProductPriceForRow(field.rowNumber, field.value);
});

crud.field('services').subfield('service_id').onChange(function(field) {
    // Hide all subfields in the current row first
    hideAllSubfields(field.rowNumber);
    
    // Then show only the relevant fields for the selected service
    if (field.value) {
        showExtraFields(field.value, field.rowNumber);
    }
});

function setSubfieldEnabled(fieldName, rowNumber, enabled) {
    try {
        var $f = crud.field('services').subfield(fieldName, rowNumber).$input;
        if ($f && $f.length) {
            $f.prop('disabled', !enabled);
            if (!enabled) $f.prop('required', false);
        }
    } catch (e) {
        var $row = $('[data-repeatable-identifier="services"][data-row-number="' + rowNumber + '"]');
        var $inp = $row.find('[name*="[' + fieldName + ']"]');
        if ($inp.length) {
            $inp.prop('disabled', !enabled);
            if (!enabled) $inp.prop('required', false);
        }
    }
}

function showExtraFields(serviceId, rowNumber) {
    $.ajax({
        url: '/admin/service/get-extra-fields/' + serviceId,
        method: 'GET',
        success: function(response) {
            for (var i = 0; i < response.extra_field_names.length; i++) {
                var fname = response.extra_field_names[i];
                crud.field('services').subfield(fname, rowNumber).show();
                setSubfieldEnabled(fname, rowNumber, true);
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
            setSubfieldEnabled(name, rowNumber, false);
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

    // Capture selected piece_id (may not be in visible fields if hidden)
    if (!formData.piece_id) {
        try {
            formData.piece_id = crud.field('services').subfield('piece_id', rowNumber).$input.val();
        } catch (e) {
            formData.piece_id = $row.find('select[name*="[piece_id]"]').val();
        }
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
                var pieceQty = getPieceQuantityById(formData.piece_id) || 1;
                var eligibleProductsCount = getEligibleProductsCount() || 1;
                var finalPrice = response.price_gel * pieceQty * eligibleProductsCount;
                crud.field('services').subfield('price_gel', rowNumber).$input.val(finalPrice);
            }
        }
    });
}

// Helper: find piece quantity by piece id from current form (or default 1)
function getPieceQuantityById(pieceId) {
    if (!pieceId) return 0;
    var pieces = getPiecesFromForm();
    for (var i = 0; i < pieces.length; i++) {
        if (String(pieces[i].id) === String(pieceId)) {
            return parseInt(pieces[i].quantity) || 1;
        }
    }
    return 0;
}

// Count selected products whose option has data-product-type of glass or mirror
function getEligibleProductsCount() {
    var count = 0;
    $('[data-repeatable-identifier="products"][data-row-number]').each(function() {
        var $row = $(this);
        var $sel = $row.find('select[name*="[product_id]"]');
        if ($sel.length) {
            var val = $sel.val();
            if (val) {
                var type = ($sel.find('option[value="' + val + '"]').attr('data-product-type') || '').toLowerCase();
                if (type === 'glass' || type === 'mirror') {
                    count++;
                }
            }
        }
    });
    return count;
}

// Function to get pieces from the pieces repeatable field
function getPiecesFromForm() {
    var pieces = [];
    var isEditPage = window.location.pathname.includes('/edit');
    var pieceIndex = 0;
    
    // Get all piece rows in order
    var $pieceRows = $('[data-repeatable-identifier="pieces"][data-row-number]').sort(function(a, b) {
        return parseInt($(a).attr('data-row-number')) - parseInt($(b).attr('data-row-number'));
    });
    
    $pieceRows.each(function() {
        var $row = $(this);
        var width = $row.find('input[name*="[width]"]').val();
        var height = $row.find('input[name*="[height]"]').val();
        var quantity = $row.find('input[name*="[quantity]"]').val();
        var rowNumber = parseInt($(this).attr('data-row-number'));
        
        if (width && height) {
            // Try to get piece ID from hidden input or data attribute (for edit page)
            var pieceId = null;
            if (isEditPage) {
                // For edit page, pieces might have IDs stored in the form
                // Check if there's a hidden input with piece ID
                var $hiddenId = $row.find('input[type="hidden"][name*="[id]"]');
                if ($hiddenId.length && $hiddenId.val()) {
                    pieceId = $hiddenId.val();
                } else {
                    // Use temporary ID based on index (will be mapped in backend)
                    pieceId = 'temp_' + pieceIndex;
                }
            } else {
                // For create page, use temporary ID based on index
                pieceId = 'temp_' + pieceIndex;
            }
            
            pieces.push({
                id: pieceId,
                width: parseFloat(width),
                height: parseFloat(height),
                quantity: parseInt(quantity) || 1,
                rowNumber: rowNumber,
                index: pieceIndex
            });
            pieceIndex++;
        }
    });
    return pieces;
}

// Function to update piece options for all service rows
function updatePieceOptionsForAllServices() {
    var pieces = getPiecesFromForm();
    // For edit page, try to get pieces from existing order
    var isEditPage = window.location.pathname.includes('/edit');
    
    if (isEditPage && typeof orderPieces !== 'undefined' && orderPieces.length > 0) {
        pieces = orderPieces.map(function(piece) {
            return {
                id: piece.id,
                width: parseFloat(piece.width),
                height: parseFloat(piece.height),
                quantity: parseInt(piece.quantity) || 1
            };
        });
    }
    
    // Update all service rows
    $('[data-repeatable-identifier="services"][data-row-number]').each(function() {
        var rowNumber = parseInt($(this).attr('data-row-number'));
        updatePieceOptionsForServiceRow(rowNumber, pieces);
    });
}

// Function to update piece options for a specific service row
function updatePieceOptionsForServiceRow(rowNumber, pieces) {
    try {
        var $select = crud.field('services').subfield('piece_id', rowNumber).$input;
        if (!$select || !$select.length) {
            // Fallback to jQuery
            var $row = $('[data-repeatable-identifier="services"][data-row-number="' + rowNumber + '"]');
            $select = $row.find('select[name*="[piece_id]"]');
        }
        
        if ($select && $select.length) {
            // Capture any existing value from multiple possible sources (select value, data attrs)
            var currentValue = $select.val();
            var dataValue = $select.data('current-value') || $select.attr('data-current-value');
            var attrValue = $select.attr('value');
            // Read persisted value saved in hidden field if present (set on edit)
            var $row = $select.closest('[data-repeatable-identifier="services"][data-row-number]');
            var hiddenSaved = $row.find('input[name*="[piece_id_saved]"]').val();
            var initialValue = currentValue || dataValue || hiddenSaved || attrValue;
            $select.empty();
            $select.append('<option value="">-</option>');
            
            pieces.forEach(function(piece) {
                var optionText = piece.width + 'x' + piece.height + ' (x' + piece.quantity + ')';
                var optionValue = piece.id;
                $select.append('<option value="' + optionValue + '">' + optionText + '</option>');
            });
            
            // Restore previous value if it still exists
            var targetValue = currentValue || initialValue;
            if (targetValue && $select.find('option[value="' + targetValue + '"]').length) {
                $select.val(targetValue);
            }
            
            // Mark as programmatic update before triggering change
            isProgrammaticPieceUpdate = true;
            // Trigger change event (this will be ignored by handler)
            $select.trigger('change');
            // Clear flag after a short delay to ensure all event handlers have processed
            setTimeout(function() {
                isProgrammaticPieceUpdate = false;
            }, 50);
        }
    } catch(e) {
        console.error('Error updating piece options for row ' + rowNumber, e);
    }
}

// Initialize subfields on page load (for edit page)
$(document).ready(function() {
    // Store initial product type
    try {
        currentProductType = crud.field('product_type').$input.val();
    } catch(e) {}

    // On edit page, refresh product options to ensure data-product-type is present and selection kept
    (function refreshProductsOnLoad() {
        if (!currentProductType) return;
        $('[data-repeatable-identifier="products"][data-row-number]').each(function() {
            var rowNumber = parseInt($(this).attr('data-row-number'));
            filterProductsForRow(rowNumber, currentProductType);
        });
    })();
    
    // For edit page, get pieces from the page (if available)
    var isEditPage = window.location.pathname.includes('/edit');
    if (isEditPage) {
        // Try to extract pieces from the form or make an AJAX call
        // For now, we'll populate from the pieces repeatable field
        setTimeout(function() {
            updatePieceOptionsForAllServices();
        }, 1000);
    }
    
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
    
    // Update piece options when pieces are added/removed/changed
    var piecesContainer = document.querySelector('[bp-field-name="pieces"]');
    if (piecesContainer) {
        var piecesObserver = new MutationObserver(function(mutations) {
            setTimeout(function() {
                updatePieceOptionsForAllServices();
            }, 300);
        });
        
        piecesObserver.observe(piecesContainer, {
            childList: true,
            subtree: true
        });
        
        // Also listen to input changes in pieces fields (width, height, and quantity)
        $(document).on('input change', '[data-repeatable-identifier="pieces"] input[name*="[width]"], [data-repeatable-identifier="pieces"] input[name*="[height]"], [data-repeatable-identifier="pieces"] input[name*="[quantity]"]', function() {
            setTimeout(function() {
                updatePieceOptionsForAllServices();
            }, 300);
        });
    }
    
    // Update piece options when a new service row is added
    $(document).on('click', '[bp-field-name="services"] button.add-repeatable-element-button', function() {
        setTimeout(function() {
            updatePieceOptionsForAllServices();
        }, 300);
    });
    
    // Validate that at least one piece is selected before form submission
    $('form').on('submit', function(e) {
        // Disable hidden service subfields to avoid invalid invisible controls blocking submit
        $('[data-repeatable-identifier="services"]').find('input, select, textarea').each(function() {
            var $input = $(this);
            var $wrapper = $input.closest('[bp-field-wrapper]');
            var visible = $input.is(':visible') && $wrapper.is(':visible');
            $input.prop('disabled', !visible);
            if (!visible) {
                $input.prop('required', false);
            }
        });

        var hasServiceWithPiece = false;
        var hasServices = false;
        
        $('[data-repeatable-identifier="services"][data-row-number]').each(function() {
            var $row = $(this);
            var serviceId = $row.find('select[name*="[service_id]"]').val();
            var pieceId = $row.find('select[name*="[piece_id]"]').val();
            
            if (serviceId) {
                hasServices = true;
                if (pieceId) {
                    hasServiceWithPiece = true;
                }
            }
        });
        
        if (hasServices && !hasServiceWithPiece) {
            e.preventDefault();
            alert('Please fill at least one piece.');
            return false;
        }
    });


    $(document).on('change', '.js-repeatable-piece', function (e) {
        if (isProgrammaticPieceUpdate) {
            //return;
        }
        
        var $select = $(this);
        var $row = $select.closest('[data-repeatable-identifier="services"][data-row-number]');
        var rowNumber = parseInt($row.attr('data-row-number'));
        
        // Get selected option text (format: "width x height (x quantity)")
        var selectedText = $select.find('option:selected').text();
        
        if (!selectedText || selectedText === '-') {
            // Clear perimeter if no piece selected
            try {
                crud.field('services').subfield('perimeter', rowNumber).$input.val('');
            } catch(e) {
                $row.find('input[name*="[perimeter]"]').val('');
            }
            return;
        }
        
        // Parse width, height, and quantity from option text (e.g., "100x200 (x1)")
        var match = selectedText.match(/(\d+(?:\.\d+)?)x(\d+(?:\.\d+)?)\s*\(x(\d+)\)/);
        if (match) {
            var width = parseFloat(match[1]);
            var height = parseFloat(match[2]);
            var quantity = parseInt(match[3]) || 1;
            
            // Calculate perimeter in meters: 2 * (width + height) / 1000 (convert mm to meters)
            // Then multiply by quantity
            var perimeter = (2 * (width + height) / 1000);
            var area = width * height / 1000000;

            // Set perimeter and area values
            try {
                crud.field('services').subfield('perimeter', rowNumber).$input.val(perimeter.toFixed(2));
                crud.field('services').subfield('area', rowNumber).$input.val(area.toFixed(2));
            } catch(e) {
                $row.find('input[name*="[perimeter]"]').val(perimeter.toFixed(2));
                $row.find('input[name*="[area]"]').val(area.toFixed(2));
            }
        }
    });
});



