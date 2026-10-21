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
        'order_id' => 'order number',
    ],

    'hints' => [
        'key' => 'Optional. A short latin name used to find this template, for example order_ready. Leave it empty if the template is only chosen by its title.',
        'text' => 'Placeholders: :variables. Name and phone come from the selected client. The cabinet link and the order number are filled when the system sends that SMS.',
    ],

    'test' => [
        'button' => 'Send test SMS',
        'title' => 'Send test SMS',
        'phone' => 'Phone',
        'phone_placeholder' => '5XX XXX XXX',
        'preview' => 'Text that will be sent',
        'hint' => 'The SMS is sent now, only to this number. {client_name} becomes “:name”, {client_phone} becomes this number, {cabinet_url} becomes a sample link, and {order_id} becomes :order.',
        'submit' => 'Send',
        'working' => 'Sending…',
        'sent' => 'The test SMS was sent.',
        'failed' => 'The test SMS could not be sent.',
        'sample_name' => 'Test client',
    ],

    'counter' => [
        'summary' => ':chars characters · :sms SMS',
        'left' => ':left more characters still fit in :sms SMS.',
        'unicode' => 'The text has Georgian or other non-Latin characters: one SMS holds 70, a longer text 67 per SMS.',
        'latin' => 'The text is Latin only: one SMS holds 160, a longer text 153 per SMS.',
        'doubles' => 'A line break, ^ { } [ ] ~ \\ | € and most emoji count as 2.',
        'samples' => 'Placeholders are counted at the length of what replaces them: :list.',
    ],

    'errors' => [
        'missing' => 'The SMS template ":key" does not exist.',
        'key_format' => 'Use lowercase latin letters, digits, and underscores, and start with a letter.',
        'key_locked' => 'This template is used by the system. Its key cannot be changed.',
        'system' => 'This template is used by the system and cannot be deleted.',
    ],
];
