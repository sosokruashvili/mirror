<?php

return [

    'entity' => 'საწყობის ერთეული',
    'entity_plural' => 'საწყობი',

    'id' => 'ID',
    'product' => 'პროდუქტი',
    'supplier' => 'მომწოდებელი',
    'quantity_of_lists' => 'ფურცლების რაოდენობა',
    'area' => 'ფართობი (მ²)',
    'created_at' => 'შექმნის თარიღი',

    // "Remaining in warehouse" widget
    'remaining' => [
        'title' => 'ნაშთი საწყობში (მ²)',
        'date' => 'თარიღი',
        'no_snapshots' => 'ჯერ არ არის სნეპშოტები',
        'product' => 'პროდუქტი',
        'all_products' => 'ყველა პროდუქტი',
        'reset' => 'გასუფთავება',
        'col_product' => 'პროდუქტი',
        'col_offcut' => 'ნარჩენი (მ²)',
        'col_in_warehouse' => 'საწყობში (მ²)',
        'col_expenses' => 'დანახარჯი (მ²)',
        'col_corrections' => 'კორექციები (მ²)',
        'col_remaining' => 'ნაშთი (მ²)',
    ],

    // Order expenses
    'expense' => [
        'entity' => 'შეკვეთის დანახარჯი',
        'entity_plural' => 'შეკვეთების დანახარჯები',
        'order_id' => 'შეკვეთის ID',
        'client' => 'კლიენტი',
        'product_type' => 'პროდუქტის ტიპი',
        'products' => 'პროდუქტები',
        'base_expense' => 'საბაზისო დანახარჯი (მ²)',
        'offcut_pct' => 'ნარჩენი (%)',
        'offcut_area' => 'ნარჩენი (მ²)',
        'expense_sum' => 'დანახარჯი ჯამში (მ²)',
        'created_at' => 'შექმნის თარიღი',
        'status' => 'სტატუსი',
        'order_date_range' => 'შეკვეთის პერიოდი',
        'stats_orders_count' => 'შეკვეთების რაოდენობა',
        'stats_total_expenses' => 'ჯამური დანახარჯი (მ²)',
    ],

    // Stock corrections
    'correction' => [
        'entity' => 'კორექცია',
        'entity_plural' => 'კორექციები',
        'id' => 'ID',
        'product' => 'პროდუქტი',
        'amount' => 'კორექცია (მ²)',
        'effective_from' => 'ძალაშია თარიღიდან',
        'reason' => 'მიზეზი',
        'entered_by' => 'შემყვანი',
        'entered_at' => 'შეყვანის თარიღი',
        'effective_date_range' => 'ძალაში შესვლის პერიოდი',
        'hints' => [
            'amount' => 'ნაშთის კორექტირება ნიშნით. გამოიყენეთ <b>უარყოფითი</b> მნიშვნელობა ჩამოსაწერად (მაგ. -12.5 გატეხვის ან ინვენტარიზაციის დანაკლისისთვის) და <b>დადებითი</b> ნაშთის დასამატებლად.',
            'effective_from' => 'კორექცია ითვლება ამ დღიდან. მანამდელი სნეპშოტები უცვლელი რჩება.',
            'reason' => 'სავალდებულოა — ეს არის ჩანაწერი იმისა, თუ რატომ შეიცვალა ნაშთი.',
        ],
    ],

];
