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

];
