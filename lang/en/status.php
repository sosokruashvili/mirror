<?php

/*
| Order statuses and piece production stages, keyed by the value stored in
| the database. Used by the status_badge() helper in app/helpers.php.
*/

return [
    'draft' => 'Draft',
    'new' => 'New',
    'pending' => 'Pending',
    'working' => 'Working',
    'done' => 'Done',
    'finished' => 'Finished',
    'ready' => 'Ready',
    'cut' => 'Cut',
    'processed' => 'Processed',
    'broken' => 'Broken',
];
