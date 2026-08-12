<?php

/*
| Warehouse module: stock items (WarehouseCrudController), the
| "Remaining in warehouse" summary widget, order expenses
| (WarehouseExpenseCrudController) and stock corrections
| (WarehouseCorrectionCrudController).
*/

return [

    'entity' => 'warehouse item',
    'entity_plural' => 'warehouses',

    'id' => 'ID',
    'product' => 'Product',
    'supplier' => 'Supplier',
    'quantity_of_lists' => 'Quantity of lists',
    'area' => 'Area (m²)',
    'created_at' => 'Created At',

    // "Remaining in warehouse" widget
    'remaining' => [
        'title' => 'Remaining in warehouse (m²)',
        'date' => 'Date',
        'no_snapshots' => 'No snapshots yet',
        'product' => 'Product',
        'all_products' => 'All products',
        'reset' => 'Reset',
        'col_product' => 'Product',
        'col_offcut' => 'Offcut (m²)',
        'col_in_warehouse' => 'In warehouse (m²)',
        'col_expenses' => 'Expenses (m²)',
        'col_corrections' => 'Corrections (m²)',
        'col_remaining' => 'Remaining (m²)',
    ],

    // Order expenses
    'expense' => [
        'entity' => 'order expense',
        'entity_plural' => 'order expenses',
        'order_id' => 'Order ID',
        'client' => 'Client',
        'product_type' => 'Product Type',
        'products' => 'Products',
        'base_expense' => 'Base Expense (m²)',
        'offcut_pct' => 'Offcut (%)',
        'offcut_area' => 'Offcut (m²)',
        'expense_sum' => 'Expense SUM (m²)',
        'created_at' => 'Created At',
        'status' => 'Status',
        'order_date_range' => 'Order Date Range',
        'stats_orders_count' => 'Orders Count',
        'stats_total_expenses' => 'Total Expenses (m²)',
    ],

    // Stock corrections
    'correction' => [
        'entity' => 'correction',
        'entity_plural' => 'corrections',
        'id' => 'ID',
        'product' => 'Product',
        'amount' => 'Correction (m²)',
        'effective_from' => 'Effective From',
        'reason' => 'Reason',
        'entered_by' => 'Entered By',
        'entered_at' => 'Entered At',
        'effective_date_range' => 'Effective Date Range',
        'hints' => [
            'amount' => 'Signed adjustment to remaining stock. Use a <b>negative</b> value to write stock off (e.g. -12.5 for breakage or a stocktake shortfall) and a <b>positive</b> value to add stock back.',
            'effective_from' => 'The correction counts from this day onward. Snapshots before it are left untouched.',
            'reason' => 'Required — this is the record of why the stock was adjusted.',
        ],
    ],

];
