<?php

return [
    'entity' => 'SMS template',
    'entity_plural' => 'SMS templates',

    'title' => 'Title',
    'text' => 'Text',
    'key' => 'Key',
    'updated_at' => 'Updated',
    'system_key' => 'The system sends this template (:key). The text can be edited. The template cannot be deleted.',

    'variables' => [
        'client_name' => 'client name',
        'client_phone' => 'client phone',
        'cabinet_url' => 'cabinet link',
    ],

    'hints' => [
        'text' => 'Placeholders: :variables. Name and phone come from the selected client. The cabinet link is filled when the system sends that SMS.',
    ],

    'errors' => [
        'missing' => 'The SMS template ":key" does not exist.',
        'system' => 'This template is used by the system and cannot be deleted.',
    ],
];
