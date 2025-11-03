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
}).change();


