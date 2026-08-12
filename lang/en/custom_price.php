<?php

/*
| Custom Prices module: per-client product prices that override the product's
| own price (CustomPriceCrudController + CustomPriceRequest).
*/

return [

    'entity' => 'custom price',
    'entity_plural' => 'custom prices',

    // Columns & fields
    'id' => 'ID',
    'client' => 'Client',
    'product' => 'Product',
    'actual_price' => 'Original Price ($)',
    'price_usd' => 'Custom Price ($)',
    'price_usd_field' => 'Price (USD)',
    'created_at' => 'Created At',

    // Validation attribute names (interpolated into :attribute messages, so
    // lowercase in English the way Laravel's own messages read).
    'attributes' => [
        'client' => 'client',
        'product' => 'product',
        'price_usd' => 'price (USD)',
    ],

    'messages' => [
        'client_required' => 'Please select a client.',
        'product_required' => 'Please select a product.',
        'product_unique' => 'This client already has a custom price for this product.',
        'price_required' => 'Please enter a price.',
        'price_numeric' => 'Price must be a valid number.',
        'price_min' => 'Price must be at least 0.',
    ],

];
