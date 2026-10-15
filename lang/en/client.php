<?php

/*
| Clients module: ClientCrudController columns/fields/filters and the
| client registration modal used from the order form.
|
| The `types` keys are the values stored in clients.client_type
| (0 = individual, 1 = legal), so they must not change - only the labels.
*/

return [

    'entity' => 'client',
    'entity_plural' => 'clients',

    // Columns & fields
    'id' => 'ID',
    'name' => 'Name',
    'author' => 'Author',
    'email' => 'Email',
    'phone' => 'Phone',
    'type' => 'Type',
    'client_type' => 'Client Type',
    'address' => 'Address',
    'personal_id' => 'Personal ID',
    'legal_id' => 'Legal ID',
    'starting_balance' => 'Starting Balance',

    'types' => [
        0 => 'Individual',
        1 => 'Legal',
    ],

    'placeholders' => [
        'personal_id' => 'Enter personal ID number',
        'legal_id' => 'Enter legal ID number',
    ],

    'hints' => [
        'client_type' => 'Select client type: Individual or Legal',
        'starting_balance' => 'Opening balance carried over from before the system. Positive = client credit, negative = client owes.',
    ],

    // Quick-create modal (opened from the order form)
    'modal' => [
        'title' => 'New Client',
        'close' => 'Close',
        'submit' => 'Create Client',
    ],

    'messages' => [
        'create_failed' => 'Failed to create client: :error',
    ],

    'cabinet' => [
        'heading' => 'Client cabinet',
        'intro' => 'The client signs in with the phone number on this form.',
        'enabled' => 'Cabinet access',
        'column' => 'Cabinet',
        'on' => 'On',
        'off' => 'Off',
        'password' => 'Cabinet password',
        'password_confirmation' => 'Confirm password',
        'password_hint' => 'At least 8 characters. Leave blank to keep the current password.',
        'password_required' => 'Set a password to turn cabinet access on.',
        'password_min' => 'The cabinet password must be at least 8 characters.',
        'password_confirmed' => 'The password confirmation does not match.',
        'phone_invalid' => 'This phone number cannot be used to sign in.',
        'phone_taken' => 'Another client already signs in with this phone number.',
        'last_login' => 'Last sign-in',
        'never_logged_in' => 'has not signed in yet',
        'send' => 'Allow Cabinet',
        'allow' => 'Allow Cabinet',
        'allow_confirm' => 'A notification will be created with this client\'s private cabinet link. The link only works with its access token. Any previous link will stop working.',
        'allow_confirm_button' => 'Allow',
        'sms_body' => 'თქვენი ელექტრონული კაბინეტის მისამართი: :url',
        'sms_confirm' => "An SMS will be queued and sent within a few minutes:\n:message",
        'sms_confirm_inactive' => "Cabinet access is off for this client. An SMS will still be queued and sent within a few minutes:\n:message",
        'sms_queued' => 'The notification was created. The SMS will be sent within a few minutes.',
        'sms_failed' => 'The notification could not be created.',
    ],

];
