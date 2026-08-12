<?php

/*
| Pieces module: PieceCrudController list/update plus the "broken glass"
| description modal (widgets/piece_broken_modal + assets/js/piece-broken-list.js).
|
| Stage labels themselves come from the DB (stages.title via piece_stages()),
| not from here. The "Draft" badge shown when a piece has no completed stage
| reuses status.draft.
*/

return [

    'entity' => 'piece',
    'entity_plural' => 'pieces',

    // Columns & fields
    'id' => 'ID',
    'order' => 'Order',
    'product' => 'Product',
    'order_product_type' => 'Order Product Type',
    'product_type' => 'Product Type',
    'stage' => 'Stage',
    'broken' => 'Broken',
    'width' => 'Width',
    'height' => 'Height',
    'quantity' => 'Quantity',
    'created_at' => 'Created At',

    // Broken-glass description modal
    'broken_modal' => [
        'title' => 'Broken – Description',
        'close' => 'Close',
        'view_description' => 'View description',
        'no_description' => 'No description provided.',
    ],

];
