<?php

return [

    'types' => [
        'Order' => 'შეკვეთა',
        'Debt' => 'ვალი',
    ],

    'methods' => [
        'Cash' => 'ნაღდი',
        'Transfer' => 'გადარიცხვა',
        'Terminal' => 'ტერმინალი',
        'PM Transfer' => 'PM გადარიცხვა',
    ],

    'entity' => 'გადახდა',
    'entity_plural' => 'გადახდები',

    // Columns & fields
    'id' => 'ID',
    'client' => 'კლიენტი',
    'author' => 'ავტორი',
    'amount_gel' => 'თანხა (₾)',
    'amount_gel_field' => 'თანხა (₾)',
    'currency_rate' => 'ვალუტის კურსი',
    'method' => 'მეთოდი',
    'payment_method' => 'გადახდის მეთოდი',
    'type' => 'ტიპი',
    'payment_type' => 'გადახდის ტიპი',
    'status' => 'სტატუსი',
    'payment_date' => 'გადახდის თარიღი',
    'order' => 'შეკვეთა',
    'orders' => 'შეკვეთები',
    'select_client_first' => '- ჯერ აირჩიეთ კლიენტი -',
    'loading_orders' => 'შეკვეთები იტვირთება...',
    'orders_load_failed' => 'შეკვეთები ვერ ჩაიტვირთა',
    // Appended to each option in the Orders select (:amount = what the order still owes).
    'order_owed' => 'დარჩენილი :amount ₾',
    // Under the Orders select once two or more are picked.
    'orders_summary' => 'არჩეულია :count შეკვეთა, დარჩენილი ვალი ჯამში :amount ₾. თანხამ ეს სრულად უნდა დაფაროს.',
    'payment_file' => 'გადახდის ფაილი',
    'client_balance' => 'კლიენტის ბალანსი',

    // payments.status values, keyed by the value stored in the database
    'statuses' => [
        'Paid' => 'გადახდილი',
        'Pending' => 'მოლოდინში',
    ],

    'filters' => [
        'amount_gel' => 'თანხა (₾)',
        'min_amount' => 'მინ. თანხა',
        'max_amount' => 'მაქს. თანხა',
        'payment_date_range' => 'გადახდის პერიოდი',
    ],

    'hints' => [
        'client' => 'აირჩიეთ კლიენტი ამ გადახდისთვის',
        'order' => 'აირჩიეთ, კლიენტის რომელ შეკვეთას ეხება ეს გადახდა',
        'orders' => 'შეგიძლიათ მონიშნოთ ერთი ან რამდენიმე შეკვეთა. რამდენიმე შეკვეთის შემთხვევაში თანხამ ყველას დარჩენილი ვალი უნდა დაფაროს.',
        'currency_rate' => 'ლარის დოლარში გადაყვანის კურსი',
        'payment_file' => 'ატვირთეთ გადახდასთან დაკავშირებული დოკუმენტი (ინვოისი, ქვითარი და ა.შ.)',
    ],

    // Stat widgets above the list
    'stats' => [
        'total_payments' => 'გადახდები სულ',
        'total_amount' => 'ჯამური თანხა (₾)',
        'paid_pending' => 'გადახდილი / მოლოდინში',
        'cash_sum' => 'ნაღდი ჯამში',
        'transfer_sum' => 'გადარიცხვა ჯამში',
        'terminal_sum' => 'ტერმინალი ჯამში',
        'pm_transfer_sum' => 'PM გადარიცხვა ჯამში',
    ],

    // Quick-add modal (opened from the order form)
    'modal' => [
        'title' => 'ახალი გადახდა',
        'select_client' => 'აირჩიეთ კლიენტი',
        'select_method' => 'აირჩიეთ მეთოდი',
        'select_status' => 'აირჩიეთ სტატუსი',
        'submit' => 'გადახდის შექმნა',
    ],

    'no_payments_on_order' => 'ამ შეკვეთაზე ჯერ გადახდები არ არის.',

    'messages' => [
        'create_failed' => 'გადახდის შექმნა ვერ მოხერხდა: :error',
        'multi_order_short' => 'თანხა (:amount ₾) არ ფარავს არჩეული შეკვეთების დარჩენილ ვალს (:needed ₾). რამდენიმე შეკვეთაზე გადახდამ ყველა სრულად უნდა დაფაროს. ნაწილობრივი გადახდისთვის აირჩიეთ ერთი შეკვეთა.',
    ],

];
