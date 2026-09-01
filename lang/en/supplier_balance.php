<?php

/*
| Supplier Balances module: read-only list of suppliers with balances computed
| from their Expenses-Purchases rows, plus the expandable per-supplier details
| row (resources/views/vendor/backpack/crud/details_rows/supplier_balance).
*/

return [

    'entity' => 'supplier balance',
    'entity_plural' => 'supplier balances',

    // Columns
    // Own key rather than supplier.entity, which is lowercase for Backpack's
    // "Add a new supplier" phrasing and reads wrong as a column header.
    'supplier' => 'Supplier',
    'id' => 'ID',
    'phone' => 'Phone',
    'email' => 'Email',
    'expenses_total' => 'Total Amount (₾)',
    'paid_total' => 'Total Paid (₾)',
    'credit_total' => 'Balance (₾)',

    // Filters
    'filters' => [
        'name' => 'Name',
        'only_debt' => 'With outstanding balance',
    ],

    // Expandable details row
    'details' => [
        'expenses_total' => 'Total Amount',
        'paid_total' => 'Total Paid',
        'credit_total' => 'Balance',

        'expenses' => 'Expenses-Purchases',
        'view_all' => 'View all',
        'no_expenses' => 'No expenses for this supplier yet.',

        'date' => 'Date',
        'type' => 'Type',
        'category' => 'Category',
        'product' => 'Product',
        'description' => 'Description',
        'amount' => 'Amount',
        'paid' => 'Paid',
        'credit' => 'Credit',
        'totals' => 'Totals',
    ],

];
