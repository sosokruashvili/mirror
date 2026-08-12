<?php

/*
| Supplier Prices module: what each supplier charges us per product.
| Covers SupplierPriceCrudController and SupplierPriceRequest.
*/

return [

    'entity' => 'supplier price',
    'entity_plural' => 'supplier prices',

    // Columns & fields
    'id' => 'ID',
    'product' => 'Product',
    'supplier' => 'Supplier',
    'price_usd' => 'Purchase Price ($)',
    'price_usd_field' => 'Purchase Price (USD)',
    'sale_price' => 'Sale Price ($)',
    'last_updated' => 'Last Updated',

    'hints' => [
        'price_usd' => 'What this supplier charges us for one unit of the product.',
    ],

    // Validation attribute names (interpolated into :attribute messages, so
    // lowercase in English the way Laravel's own messages read).
    'attributes' => [
        'supplier' => 'supplier',
        'product' => 'product',
        'price_usd' => 'purchase price (USD)',
    ],

    'messages' => [
        'supplier_required' => 'Please select a supplier.',
        'product_required' => 'Please select a product.',
        'product_unique' => 'This supplier already has a price for this product.',
        'price_required' => 'Please enter a purchase price.',
        'price_numeric' => 'Purchase price must be a valid number.',
        'price_min' => 'Purchase price must be at least 0.',
    ],

];
