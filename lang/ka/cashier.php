<?php

return [

    'entity' => 'სალარო',
    'entity_plural' => 'სალარო',

    // Columns
    'balance_date' => 'თარიღი',
    'amount' => 'დღის ბოლოს ბალანსი (₾)',
    'created_at' => 'ფიქსაციის დრო',

    'filters' => [
        'date_range' => 'პერიოდი',
    ],

    // Today-stats widget above the list
    'stats' => [
        'current_balance' => 'მიმდინარე ბალანსი',
        'opening' => 'საწყისი (დღეს)',
        'cash_in' => 'ნაღდის შემოსვლა (დღეს)',
        'cash_out' => 'ნაღდის გასვლა (დღეს)',
    ],

    // Recalculate button
    'recalculate' => [
        'button' => 'ბალანსების გადაანგარიშება',
        'running' => 'მიმდინარეობს გადაანგარიშება...',
        'success' => 'სალაროს ბალანსები გადაანგარიშდა.',
        'error' => 'სალაროს ბალანსების გადაანგარიშება ვერ მოხერხდა. გთხოვთ სცადოთ ხელახლა.',
        'done' => 'სალაროს ბალანსი გადაანგარიშდა :count დღისთვის.',
    ],

    // Expandable details row
    'details' => [
        'opening_balance' => 'საწყისი ბალანსი',
        'opening_hint' => 'წინა დღის ბოლოს ბალანსი',
        'cash_in' => 'ნაღდის შემოსვლა',
        'cash_in_hint' => ':count ნაღდი გადახდა',
        'cash_out' => 'ნაღდის გასვლა',
        'cash_out_hint' => ':count ნაღდი ხარჯი',
        'closing_balance' => 'დღის ბოლოს ბალანსი',
        'net_change' => 'წმინდა ცვლილება:',

        'drift_title' => 'ფიქსირებული მონაცემი მოძველებულია:',
        'drift_body' => 'მიმდინარე მონაცემებით გადაანგარიშება იძლევა',
        'drift_stored' => 'ხოლო ფიქსირებული მნიშვნელობაა',
        'drift_reason' => 'სავარაუდოდ, გადახდები ან ხარჯები ფიქსაციის შემდეგ შეიცვალა.',

        'payments' => 'ნაღდი გადახდები',
        'expenses' => 'ნაღდი ხარჯები',
        'view_all' => 'ყველას ნახვა',
        'no_payments' => 'ამ დღეს ნაღდი გადახდები არ არის.',
        'no_expenses' => 'ამ დღეს ნაღდი ხარჯები არ არის.',

        'time' => 'დრო',
        'client' => 'კლიენტი',
        'order' => 'შეკვეთა',
        'type' => 'ტიპი',
        'category' => 'კატეგორია',
        'description' => 'აღწერა',
        'amount' => 'თანხა',
        'credit' => 'დავალიანება',
        'paid' => 'გადახდილი',
        'total_cash_in' => 'ნაღდის შემოსვლა სულ',
        'total_cash_out' => 'ნაღდის გასვლა სულ',
    ],

];
