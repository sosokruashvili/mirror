crud.field('product_type').onChange(function(field) {
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
}).change();


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
});


