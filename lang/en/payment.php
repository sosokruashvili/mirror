<?php

/*
| Payment types and methods. Keys are the values stored in payments.type /
| payments.method, so they must never change - only the labels are translated.
*/

return [

    'types' => [
        'Order' => 'Order',
        'Debt' => 'Debt',
    ],

    'methods' => [
        'Cash' => 'Cash',
        'Transfer' => 'Transfer',
        'Terminal' => 'Terminal',
        'PM Transfer' => 'PM Transfer',
    ],

    'entity' => 'payment',
    'entity_plural' => 'payments',

    // Columns & fields
    'id' => 'ID',
    'client' => 'Client',
    'author' => 'Author',
    'amount_gel' => 'Amount (₾)',
    'amount_gel_field' => 'Amount GEL',
    'currency_rate' => 'Currency Rate',
    'method' => 'Method',
    'payment_method' => 'Payment Method',
    'type' => 'Type',
    'payment_type' => 'Payment Type',
    'status' => 'Status',
    'payment_date' => 'Payment Date',
    'order' => 'Order',
    'payment_file' => 'Payment File',
    'client_balance' => 'Client Balance',

    // payments.status values, keyed by the value stored in the database
    'statuses' => [
        'Paid' => 'Paid',
        'Pending' => 'Pending',
    ],

    'filters' => [
        'amount_gel' => 'Amount GEL',
        'min_amount' => 'Min amount',
        'max_amount' => 'Max amount',
        'payment_date_range' => 'Payment Date Range',
    ],

    'hints' => [
        'client' => 'Select the client for this payment',
        'order' => "Select which of the client's orders this payment is for",
        'currency_rate' => 'Exchange rate for GEL to USD',
        'payment_file' => 'Upload payment related document (invoice, receipt, etc.)',
    ],

    // Stat widgets above the list
    'stats' => [
        'total_payments' => 'Total Payments',
        'total_amount' => 'Total Amount GEL',
        'paid_pending' => 'Paid / Pending',
        'cash_sum' => 'Cash SUM',
        'transfer_sum' => 'Transfer SUM',
        'terminal_sum' => 'Terminal SUM',
        'pm_transfer_sum' => 'PM Transfer SUM',
    ],

    // Quick-add modal (opened from the order form)
    'modal' => [
        'title' => 'New Payment',
        'select_client' => 'Select Client',
        'select_method' => 'Select Method',
        'select_status' => 'Select Status',
        'submit' => 'Create Payment',
    ],

    'no_payments_on_order' => 'No payments on this order yet.',

    'messages' => [
        'create_failed' => 'Failed to create payment: :error',
    ],

];
