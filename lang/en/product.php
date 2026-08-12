<?php

/*
| Products module: ProductCrudController columns, filters and fields.
| Material types come from the material_type lang file.
*/

return [

    'entity' => 'product',
    'entity_plural' => 'products',

    'id' => 'ID',
    'title' => 'Title',
    'product_type' => 'Product Type',
    'price_usd' => 'Price ($)',
    'wholesale_price_usd' => 'Wholesale Price ($)',
    'offcut' => 'Offcut (%)',

    'retail_price_field' => 'Retail Price (USD)',
    'wholesale_price_field' => 'Wholesale Price (USD)',

    'filters' => [
        'retail_price' => 'Retail Price',
        'wholesale_price' => 'Wholesale Price',
        'order' => 'Order',
    ],

    'hints' => [
        'wholesale_price' => 'Optional wholesale price for bulk purchases',
        'offcut' => 'Percent of piece area added on top when calculating warehouse expenses for orders using this product.',
    ],

];
