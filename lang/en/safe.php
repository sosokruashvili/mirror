<?php

return [

    'entity' => 'Safe',
    'entity_plural' => 'Safe',

    'author' => 'Author',
    'status' => 'Status',
    'direction' => 'Direction',
    'amount_gel' => 'Amount (₾)',
    'amount_gel_field' => 'Amount (₾)',
    'description' => 'Note',
    'transferred_at' => 'Date',

    'directions' => [
        'to_safe' => 'Cashier to safe',
        'to_cashier' => 'Safe to cashier',
    ],

    'filters' => [
        'date_range' => 'Date range',
    ],

    'hints' => [
        'status' => 'A draft can be edited and moves no money. Confirming it locks the row and moves the amount.',
        'direction' => 'A deposit into the safe leaves the cashier. A return puts the amount back in the cashier.',
    ],

    'stats' => [
        'balance' => 'Safe balance',
        'balance_hint' => 'Deposits − returns − expenses paid from the safe',
        'deposited' => 'Deposited',
        'returned' => 'Returned to cashier',
        'expenses' => 'Paid from safe',
    ],

    'attributes' => [
        'direction' => 'Direction',
        'status' => 'Status',
        'amount_gel' => 'Amount (₾)',
        'description' => 'Note',
        'transferred_at' => 'Date',
    ],

];
