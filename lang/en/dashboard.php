<?php

/*
| Dashboard widgets: the stat cards on resources/views/vendor/backpack/ui/dashboard.blade.php
| and the chart widgets under resources/views/vendor/backpack/ui/widgets/.
|
| Keys under "chart" are shared by every chart widget (date pickers, period
| toggles). Keys nested per widget are specific to that card. Strings consumed
| by Chart.js are handed to the browser as a @json(__('dashboard...')) blob.
*/

return [

    // Stat cards
    'stats' => [
        'total_clients' => 'Total Clients',
        'total_clients_hint' => 'All registered clients',
        'total_orders' => 'Total Orders',
        'total_orders_hint' => 'Confirmed orders (excluding drafts)',
        'total_products' => 'Total Products',
        'total_products_hint' => 'Available products',
        'total_pieces' => 'Total Pieces',
        'total_pieces_hint' => 'Production pieces (excluding drafts)',
    ],

    // Controls shared by every chart card
    'chart' => [
        'from' => 'From',
        'to' => 'To',
        'apply' => 'Apply',
        'reset_range' => 'Reset range',
        'last_30_days' => 'Last 30 days',
        'period' => 'Chart period',
        'by_day' => 'By day',
        'by_month' => 'By month',
        'by_year' => 'By year',
        'total' => 'Total',
        'load_error' => 'Failed to load chart data',
    ],

    'payments' => [
        'title' => 'Daily Payments by Method & Type',
        'subtitle' => 'Paid payment totals by method and type, based on payment date',
        'count' => 'Payments',
        'axis' => 'Payments (₾)',
    ],

    'daily' => [
        'title' => 'Daily Orders & Income',
        'subtitle' => 'Orders count and income (paid / credit) per period, excluding draft orders',
        'orders' => 'Orders',
        'income' => 'Income',
        'paid' => 'Paid',
        'credit' => 'Credit (owed)',
        'orders_count' => 'Orders count',
        'axis_income' => 'Income (₾)',
        'axis_orders' => 'Orders',
    ],

    'product_type' => [
        'title' => 'Orders by Product Type',
        'subtitle' => 'Orders count and total value per product type, excluding draft orders',
        'orders' => 'Orders',
        'total_value' => 'Total value',
        'value' => 'Value (₾)',
        'orders_count' => 'Orders count',
        'axis_orders' => 'Orders',
    ],

    'product_type_pie' => [
        'title' => 'Orders by Product Type',
        'subtitle' => 'Share of confirmed orders per product type, excluding draft orders',
        'metric' => 'Pie chart metric',
        'by_count' => 'By orders count',
        'by_income' => 'By income',
        'total_orders' => 'Total orders',
        'income' => 'Income (₾)',
        'orders_count' => 'Orders count',
    ],

    'area' => [
        'title' => 'Orders Area Summary',
        'subtitle' => 'Total piece area (m²), excluding draft orders and draft pieces',
        'days_30' => '30 Days',
        'months_12' => '12 Months',
        'years_10' => '10 Years',
        'total' => 'Total:',
        'unit' => 'm²',
        'label' => 'Area (m²)',
    ],

    'usd' => [
        'title' => 'USD Exchange Rate',
        'last_updated' => 'Last updated: :time',
        'unavailable' => 'N/A',
        'no_data' => 'No exchange rate data available',
    ],

];
