<?php

/*
| Global Settings page (SettingController + resources/views/admin/settings.blade.php).
|
| The `groups`, `labels` and `descriptions` arrays translate rows of the
| settings table, keyed by settings.group / settings.key. They are optional:
| anything without an entry falls back to the value stored in the database,
| so adding a new setting never breaks the page.
*/

return [

    'title' => 'Global Settings',
    'subtitle' => 'Application-wide parameters.',
    'general_group' => 'General',
    'empty' => 'No settings defined yet.',
    'save' => 'Save',
    'unit_mm' => 'mm',
    'saved' => 'Settings saved.',

    // Developer tools card (dev environments only)
    'dev' => [
        'title' => 'Developer tools',
        'description' => 'Replace this dev database with a fresh copy of production (:source).',
        'warning' => 'This <strong>erases all data on dev</strong> and cannot be undone. It takes a few seconds.',
        'confirm' => 'Erase the dev database and replace it with a copy of production? This cannot be undone.',
        'button' => 'Sync DB from Production',
        'unavailable' => 'Database sync is not available in this environment.',
        'synced' => 'Database synced from production.',
        'failed' => 'Database sync failed: :error',
        'no_output' => 'no output — see the log for details',
    ],

    // Rows of the settings table
    'groups' => [
        'Production' => 'Production',
    ],

    'labels' => [
        'cutting_size' => 'Cutting Size',
    ],

    'descriptions' => [
        'cutting_size' => 'Cutting size in millimetres (mm).',
    ],

];
