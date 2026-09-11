<?php

/*
| Broken Glasses page (BrokenGlassCrudController + the expandable
| details_rows/broken_glass row).
|
| One row = one piece that has broken at least once. "Breaks" counts break events
| (= extra sheets charged to the order); "Pieces affected" sums broken_glasses.quantity
| (= pieces sent back through production). The two differ only for size-group breaks.
|
| "Author" is whoever recorded the break in the app, NOT necessarily the person
| who physically broke the glass — do not translate it as "broke it".
|
| The "no description" fallback inside the details row reuses piece.broken_modal.
*/

return [

    'entity' => 'broken glass',
    'entity_plural' => 'broken glasses',

    // Columns
    'piece_id' => 'Piece ID',
    'order_id' => 'Order ID',
    'client' => 'Client',
    'glass_type' => 'Glass Type',
    'size' => 'Size (cm)',
    'breaks' => 'Breaks',
    'pieces_affected' => 'Pieces Affected',
    'first_break' => 'First Break',
    'last_break' => 'Last Break',

    // Filters
    'date' => 'Break Date',
    'author' => 'Author',
    'repeat_only' => 'Broken more than once',

    // Expandable details row
    'details' => [
        'date' => 'Date',
        'author' => 'Author',
        'quantity' => 'Pieces',
        'description' => 'Reason',
        'empty' => 'No break records.',
    ],

];
