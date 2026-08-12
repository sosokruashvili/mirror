<?php

return [

    'entity' => 'კლიენტის ბალანსი',
    'entity_plural' => 'კლიენტების ბალანსი',

    // Columns
    // Own key rather than client.entity, which is lowercase for Backpack's
    // "Add a new client" phrasing and reads wrong as a column header.
    'client' => 'კლიენტი',
    'starting_balance' => 'საწყისი ბალანსი (₾)',
    'payments_total_gel' => 'გადახდები სულ (₾)',
    'orders_total_gel' => 'შეკვეთები სულ (₾)',
    'balance_gel' => 'ბალანსი (₾)',

    // Filters
    'filters' => [
        'balance_date' => 'ბალანსი თარიღისთვის',
        'payments_total' => 'გადახდები სულ',
        'orders_total' => 'შეკვეთები სულ',
        'balance' => 'ბალანსი',
        'min' => 'მინ.',
        'max' => 'მაქს.',
    ],

    // Stats widget above the list
    'stats' => [
        'clients_count' => 'კლიენტების რაოდენობა',
        'total_starting' => 'საწყისი სულ',
        'total_payments' => 'გადახდები სულ',
        'total_orders' => 'შეკვეთები სულ',
        'total_balance' => 'ბალანსი სულ',
    ],

    // Recalculate button
    'recalculate' => [
        'button' => 'ბალანსების გადაანგარიშება',
        'running' => 'მიმდინარეობს გადაანგარიშება...',
        'success' => 'ბალანსები გადაანგარიშდა.',
        'error' => 'ბალანსების გადაანგარიშება ვერ მოხერხდა. გთხოვთ სცადოთ ხელახლა.',
        'done' => 'ბალანსი გადაანგარიშდა :count კლიენტისთვის.',
    ],

    // Expandable details row
    'details' => [
        'starting_balance' => 'საწყისი ბალანსი',
        'payments_total' => 'გადახდები სულ',
        'orders_total' => 'შეკვეთები სულ',
        'balance' => 'ბალანსი',

        'payments' => 'გადახდები',
        'orders' => 'შეკვეთები',
        'view_all' => 'ყველას ნახვა',
        'no_payments' => 'ამ კლიენტს ჯერ გადახდები არ აქვს.',
        'no_orders' => 'ამ კლიენტს ჯერ შეკვეთები არ აქვს.',

        'counted_in_balance' => 'ბალანსში ჩათვლილი',
        'counted_paid' => ':count გადახდილი',
        'counted_non_draft' => ':count არა-დრაფტი',

        'date' => 'თარიღი',
        'method' => 'მეთოდი',
        'type' => 'ტიპი',
        'order' => 'შეკვეთა',
        'status' => 'სტატუსი',
        'amount' => 'თანხა',
        'paid' => 'გადახდილი',
        'unpaid' => 'გადაუხდელი',
        'total' => 'ჯამი',
    ],

];
