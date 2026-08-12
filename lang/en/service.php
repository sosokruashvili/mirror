<?php

/*
| Services module: ServiceCrudController plus the Service Stats page
| (resources/views/admin/service-stats.blade.php).
|
| The extra-field option labels live in the service_pivot lang file, since
| those keys are the pivot column names shared with orders/pieces.
| Stage names come from the stages table - database content, not translated.
*/

return [

    'entity' => 'service',
    'entity_plural' => 'services',

    'id' => 'ID',
    'title' => 'Title',
    'short_name' => 'Short Name',
    'slug' => 'Slug',
    'stage' => 'Stage',
    'description' => 'Description',
    'unit' => 'Unit',
    'price_usd' => 'Price ($)',
    'price_gel' => 'Price (₾)',
    'price_usd_field' => 'Price (USD)',
    'price_gel_field' => 'Price (GEL)',
    'cutting_loss' => 'Cutting Loss (mm)',
    'extra_field_names' => 'Extra Field Names',

    'filters' => [
        'order' => 'Order',
    ],

    'placeholders' => [
        'stage' => '-',
        'unit' => 'e.g., hour, piece, sq ft',
    ],

    'hints' => [
        'short_name' => 'Short name or abbreviation for this service',
        'slug' => 'URL-friendly version of the title',
        'stage' => 'Which production stage this service belongs to.',
        'unit' => 'Unit of measurement for this service',
        'cutting_loss' => "Extra size (whole mm) added to a piece's width and height on the team orders page when this service is applied.",
    ],

    // Service Stats page
    'stats' => [
        'title' => 'Service Stats',
        'subtitle' => 'Completed work quantity and total amount per service.',
        'from' => 'From (completion date)',
        'to' => 'To',
        'filter' => 'Filter',
        'reset' => 'Reset',
        'service_name' => 'Service name',
        'stage' => 'Stage',
        'unit' => 'Unit',
        'quantity_done' => 'Quantity completed',
        'money_payable' => 'Amount payable (₾)',
        'empty' => 'No data found.',
        'grand_total' => 'Grand total',
    ],

];
