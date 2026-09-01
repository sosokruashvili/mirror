<?php

/*
| Expenses-Purchases module (CashierExpenseCrudController + its stats widget
| and CashierExpenseRequest).
|
| The `status` values (draft / confirmed) are stored in cashier_expenses.status
| and their labels live in the shared status.php file - CashierExpense::statuses()
| reads them from there. A draft expense counts nowhere.
|
| The `type` values (Cash / Transfer / PM Transfer) are stored in
| cashier_expenses.type and are shared with payments, so their labels live in
| payment.methods - CashierExpense::types() reads them from there.
|
| Category names come from the DB (expense_categories.name) and are not
| translatable here.
*/

return [

    'entity' => 'expense-purchase',
    'entity_plural' => 'Expenses-Purchases',

    // Columns & fields
    'id' => 'ID',
    'status' => 'Status',
    'type' => 'Type',
    'category' => 'Category',
    'supplier' => 'Supplier',
    'product' => 'Product',
    'price_usd' => 'Purchase Price ($)',
    'price_usd_field' => 'Purchase Price (USD)',
    'amount_gel' => 'Amount (₾)',
    'amount_gel_field' => 'Amount (GEL)',
    'credit' => 'Credit (₾)',
    'credit_field' => 'Credit (GEL)',
    'description' => 'Description',
    'file' => 'File',
    'expense_date' => 'Date',

    'filters' => [
        'date_range' => 'Date Range',
    ],

    'placeholders' => [
        'product' => 'Search and select a product',
        'price_usd' => 'Select supplier and product',
    ],

    'hints' => [
        'status' => 'Draft expenses are editable and are not counted anywhere. Confirming an expense locks it and lets it into the cashier and supplier balances.',
        'supplier' => 'Only suppliers linked to the selected category.',
        'product' => 'Only for საწარმოო purchases.',
        'price_usd' => 'Auto-filled from Supplier Prices when the supplier and product match.',
        'amount_gel' => 'Amount paid now. Enter 0 if the whole purchase is on credit.',
        'credit' => 'Unpaid portion. For a 1000 ₾ purchase paid nothing, enter amount 0 and credit 1000.',
        'file' => 'Allowed types: PDF, PNG, JPEG, JPG',
    ],

    // Appended to a category that stopped being a leaf after children were added
    'category_has_children' => ':name (has children — pick a leaf)',

    // Stats widget above the list
    'stats' => [
        'confirmed_only' => 'Confirmed expenses only — drafts are not counted.',
        'total_amount' => 'Total Amount',
        'total_credit' => 'Total Credit',
        'total_cash' => 'Cash Paid',
        'total_transfer' => 'Transfer Paid',
        'total_pm_transfer' => 'PM Transfer Paid',
    ],

    // Validation attribute names (interpolated into :attribute messages, so
    // lowercase in English the way Laravel's own messages read).
    'attributes' => [
        'type' => 'type',
        'status' => 'status',
        'category' => 'category',
        'supplier' => 'supplier',
        'product' => 'product',
        'price_usd' => 'purchase price (USD)',
        'amount_gel' => 'amount (GEL)',
        'credit' => 'credit',
        'description' => 'description',
        'file' => 'file',
        'expense_date' => 'expense date',
    ],

    'messages' => [
        'category_not_leaf' => 'Please select a leaf category (one without child categories).',
        'supplier_not_linked' => 'The selected supplier is not linked to this category.',
        'amount_or_credit_required' => 'Enter a paid amount, a credit amount, or both.',
    ],

];
