crud.field('order_product_type').onChange(function(field) {
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
    
    // Update all product select2 fields with the new order_product_type filter
    $('[bp-field-name="products"] select[name*="product_id"]').each(function() {
        var $select = $(this);
        
        // Clear current selection
        $select.val(null).trigger('change');
        
        // Update the ajax URL with the new order_product_type
        if ($select.data('select2')) {
            $select.select2('destroy');
        }
        
        // Reinitialize select2 with updated params
        $select.select2({
            ajax: {
                url: '/api/products-filtered',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term,
                        order_product_type: field.value
                    };
                },
                processResults: function (data) {
                    return {
                        results: data
                    };
                }
            },
            placeholder: 'Select a product',
            minimumInputLength: 0,
            allowClear: true
        });
    });
    
}).change();



