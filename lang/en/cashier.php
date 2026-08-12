<?php

/*
| Cashier module: the daily cash-balance snapshots (CashierCrudController),
| the today-stats widget, the Recalculate button, and the expandable per-day
| details row (details_rows/cashier_balance).
*/

return [

    'entity' => 'cashier',
    'entity_plural' => 'cashier',

    // Columns
    'balance_date' => 'Date',
    'amount' => 'Closing Balance (₾)',
    'created_at' => 'Snapshot At',

    'filters' => [
        'date_range' => 'Date Range',
    ],

    // Today-stats widget above the list
    'stats' => [
        'current_balance' => 'Current Balance',
        'opening' => 'Opening (Today)',
        'cash_in' => 'Cash In (Today)',
        'cash_out' => 'Cash Out (Today)',
    ],

    // Recalculate button
    'recalculate' => [
        'button' => 'Recalculate Balances',
        'running' => 'Recalculating...',
        'success' => 'Cashier balances recalculated.',
        'error' => 'Failed to recalculate cashier balances. Please try again.',
        'done' => 'Recalculated cashier balance for :count day(s).',
    ],

    // Expandable details row
    'details' => [
        'opening_balance' => 'Opening Balance',
        'opening_hint' => "previous day's closing",
        'cash_in' => 'Cash In',
        'cash_in_hint' => ':count cash payment(s)',
        'cash_out' => 'Cash Out',
        'cash_out_hint' => ':count cash expense(s)',
        'closing_balance' => 'Closing Balance',
        'net_change' => 'net change:',

        'drift_title' => 'Snapshot out of date:',
        'drift_body' => 'recalculating from current data gives',
        'drift_stored' => 'but the stored snapshot is',
        'drift_reason' => 'Payments or expenses were likely changed after the snapshot was taken.',

        'payments' => 'Cash Payments',
        'expenses' => 'Cash Expenses',
        'view_all' => 'View all',
        'no_payments' => 'No cash payments on this day.',
        'no_expenses' => 'No cash expenses on this day.',

        'time' => 'Time',
        'client' => 'Client',
        'order' => 'Order',
        'type' => 'Type',
        'category' => 'Category',
        'description' => 'Description',
        'amount' => 'Amount',
        'credit' => 'Credit',
        'paid' => 'Paid',
        'total_cash_in' => 'Total cash in',
        'total_cash_out' => 'Total cash out',
    ],

];
