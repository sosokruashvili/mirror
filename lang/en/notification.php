<?php

return [
    'entity' => 'notification',
    'entity_plural' => 'notifications',

    'id' => 'ID',
    'type' => 'Type',
    'recipient' => 'Recipient',
    'body' => 'Text',
    'status' => 'Status',
    'attempts' => 'Attempts',
    'sent_at' => 'Sent at',
    'created_at' => 'Created',
    'last_error' => 'Error',
    'author' => 'Author',
    'subject' => 'About',
    'gateway_reference' => 'Gateway reference',
    'client' => 'Client',
    'select_client' => 'Select a client',
    'template' => 'Template',
    'select_template' => 'Select a template',
    'in_cabinet' => 'In the cabinet',

    'types' => [
        'sms' => 'SMS',
        'cabinet' => 'Cabinet',
    ],

    'statuses' => [
        'pending' => 'Pending',
        'sending' => 'Sending',
        'sent' => 'Sent',
        'failed' => 'Failed',
    ],

    'hints' => [
        'recipient' => 'Mobile number. A local number is fine (5xx xxx xxx).',
        'queued' => 'Saving does not send the message. Pending SMS notifications are sent every 2 minutes. An empty text is taken from the template. {client_name} and {client_phone} are filled from the selected client. {cabinet_url} is filled when the system sends the cabinet link.',
        'cabinet' => 'This text is shown only in the selected client\'s cabinet. It is not sent as an SMS.',
        'template' => 'The saved message keeps this wording. Later edits to the template do not change it.',
        'client' => 'Required for a cabinet message. For an SMS, the client fills the phone number and the template placeholders.',
    ],

    'errors' => [
        'type' => 'This notification type is not supported.',
        'phone' => 'This phone number cannot receive an SMS.',
        'recipient' => 'A recipient is required.',
        'body' => 'The message text is required.',
        'too_long' => 'The message is longer than 1000 characters.',
        'client' => 'Choose a client.',
        'template' => 'An SMS template is required.',
        'unresolved' => 'Replace these placeholders before saving: :variables',
    ],
];
