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

    // Developer tools card (developer on the development DB only)
    'dev' => [
        'title' => 'Developer tools',
        'description' => 'Replace the development database :target with a fresh copy of production (:source).',
        'warning' => 'This <strong>erases all data in :target</strong> (a backup is written to storage/app/db-backups first). Production is never touched. It takes a few seconds.',
        'confirm' => 'Erase the development database ":target" and replace it with a copy of production?',
        'type_to_confirm' => 'Type :target to confirm',
        'confirm_mismatch' => 'Confirmation did not match the development database name (:db). Nothing was changed.',
        'button' => 'Copy Production → Development',
        'unavailable' => 'Database sync is only available to developers while working on the development database.',
        'synced' => 'Database synced from production.',
        'failed' => 'Database sync failed: :error',
        'no_output' => 'no output — see the log for details',
    ],

    // Rows of the settings table
    'groups' => [
        'Production' => 'Production',
        'SMS' => 'SMS',
    ],

    'labels' => [
        'cutting_size' => 'Cutting Size',
        'order_ready_sms' => 'Order ready SMS',
    ],

    'descriptions' => [
        'cutting_size' => 'Cutting size in millimetres (mm).',
        'order_ready_sms' => 'When on, the client gets an SMS (template order_ready) once an order becomes ready. When off, no new SMS is queued.',
    ],

];
