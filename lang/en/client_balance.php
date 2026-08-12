<?php

/*
| Client Balances module: ClientBalanceCrudController, the balance stats
| widget, the "Recalculate Balances" button and the expandable details row
| that lists a client's payments and orders.
|
| Client-level labels (name, phone, type, ...) are reused from the client
| lang file; only balance-specific wording lives here.
*/

return [

    'entity' => 'client balance',
    'entity_plural' => 'client balances',

    // Columns
    // Own key rather than client.entity, which is lowercase for Backpack's
    // "Add a new client" phrasing and reads wrong as a column header.
    'client' => 'Client',
    'starting_balance' => 'Starting Balance (₾)',
    'payments_total_gel' => 'Payments Total (₾)',
    'orders_total_gel' => 'Orders Total (₾)',
    'balance_gel' => 'Balance (₾)',

    // Filters
    'filters' => [
        'balance_date' => 'Balance as of date',
        'payments_total' => 'Payments Total',
        'orders_total' => 'Orders Total',
        'balance' => 'Balance',
        'min' => 'Min',
        'max' => 'Max',
    ],

    // Stats widget above the list
    'stats' => [
        'clients_count' => 'Clients Count',
        'total_starting' => 'Total Starting',
        'total_payments' => 'Total Payments',
        'total_orders' => 'Total Orders',
        'total_balance' => 'Total Balance',
    ],

    // Recalculate button
    'recalculate' => [
        'button' => 'Recalculate Balances',
        'running' => 'Recalculating...',
        'success' => 'Balances recalculated.',
        'error' => 'Failed to recalculate balances. Please try again.',
        'done' => 'Recalculated balances for :count client(s).',
    ],

    // Expandable details row
    'details' => [
        'starting_balance' => 'Starting Balance',
        'payments_total' => 'Payments Total',
        'orders_total' => 'Orders Total',
        'balance' => 'Balance',

        'payments' => 'Payments',
        'orders' => 'Orders',
        'view_all' => 'View all',
        'no_payments' => 'No payments for this client yet.',
        'no_orders' => 'No orders for this client yet.',

        'counted_in_balance' => 'Counted in balance',
        'counted_paid' => ':count paid',
        'counted_non_draft' => ':count non-draft',

        'date' => 'Date',
        'method' => 'Method',
        'type' => 'Type',
        'order' => 'Order',
        'status' => 'Status',
        'amount' => 'Amount',
        'paid' => 'Paid',
        'unpaid' => 'Unpaid',
        'total' => 'Total',
    ],

];
