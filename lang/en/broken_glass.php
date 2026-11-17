<?php

/*
| Broken Glasses page (BrokenGlassCrudController): one row per break event.
|
| "Author" is whoever recorded the break in the app, NOT necessarily the person
| who physically broke the glass — do not translate it as "broke it".
|
| "Usable" is the part of the broken sheet that can still be used; confirming a
| record returns it to stock. "Legacy" rows predate the machine/stage/usable fields.
*/

return [

    'entity' => 'broken glass',
    'entity_plural' => 'broken glasses',

    // Columns and filters
    'date' => 'Date',
    'order_id' => 'Order ID',
    'piece_id' => 'Size ID',
    'client' => 'Client',
    'glass_type' => 'Glass Type',
    'size' => 'Size (cm)',
    'damaged_area' => 'Damaged (m²)',
    'usable_area' => 'Usable (m²)',
    'loss_area' => 'Loss (m²)',
    'machine' => 'Machine',
    'detected_stage' => 'Stage found',
    'comment' => 'Comment',
    'author' => 'Recorded by',
    'status' => 'Status',
    'confirmed' => 'Confirmed by',

    'statuses' => [
        'pending' => 'To confirm',
        'confirmed' => 'Confirmed',
        'legacy' => 'Legacy',
    ],

    'totals' => [
        'entries' => 'Records',
        'damaged' => 'Damaged',
        'usable' => 'Usable',
        'loss' => 'Loss',
        'hint' => 'Totals follow the filters. Legacy records are not counted in the loss.',
    ],

    'fields' => [
        'usable_area_hint' => 'At most :max m² (one sheet). Enter 0 if nothing is usable.',
        'comment_hint' => 'A comment is required for the "Other" machine.',
    ],

    'buttons' => [
        'confirm' => 'Confirm',
        'unconfirm' => 'Undo confirmation',
    ],

    'confirm_dialog' => [
        'title' => 'Confirm this record?',
        'text_return' => ':area m² will go back to stock. The record cannot be edited after confirming.',
        'text_nothing' => 'Nothing goes back to stock (0 m² usable). The record cannot be edited after confirming.',
        'done' => 'Record confirmed.',
    ],

    'unconfirm_dialog' => [
        'title' => 'Undo the confirmation?',
        'text' => 'The m² returned to stock will be removed and the record becomes editable again.',
        'done' => 'Confirmation undone.',
    ],

    'errors' => [
        'legacy' => 'A legacy record has no machine, so it cannot be confirmed.',
        'already_confirmed' => 'This record is already confirmed.',
        'not_confirmed' => 'This record is not confirmed.',
        'failed' => 'The action failed. Reload the page and try again.',
    ],

    // Reason on the warehouse correction that returns usable m² (stored in Georgian).
    'correction_reason' => 'Broken glass #:id, order #:order, size #:piece — usable part',

];
