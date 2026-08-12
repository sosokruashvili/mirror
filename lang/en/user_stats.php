<?php

/*
| User Stats page (resources/views/admin/user-stats.blade.php) and its two
| chart widgets: top_users_chart and user_stage_completions_chart.
|
| Note: the stage names inside the completions chart come from the stages
| table, not from here - they are database content.
*/

return [

    'title' => 'User Stats',
    'subtitle' => 'Widgets about user activity and performance.',

    // Range toggle shared by both widgets
    'range' => [
        'label' => 'Range',
        'aria' => 'Chart range',
        'this_week' => 'This week',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'last_year' => 'Last year',
    ],

    // Fallback when an order/completion has no resolvable user
    'unknown_user' => 'User #:id',

    'top_users' => [
        'title' => 'Top Users by Orders',
        'subtitle' => 'Top 10 authors by order count, with total value — excluding draft orders',
        'orders_top10' => 'Orders (top 10)',
        'value_top10' => 'Total value (top 10)',
        'orders_count' => 'Orders count',
        'value' => 'Value (₾)',
        'axis_orders' => 'Orders',
    ],

    'stage_completions' => [
        'title' => 'Top Users by Stage Completions',
        'subtitle' => 'Top 10 workers by production stages finished, broken down by stage — excluding draft orders and the auto-completed final stage',
        'completions_top10' => 'Completions (top 10)',
        'active_users' => 'Active users',
        'axis_stages' => 'Stages completed',
    ],

];
