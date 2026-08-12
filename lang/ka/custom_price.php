<?php

return [

    'entity' => 'ინდივიდუალური ფასი',
    'entity_plural' => 'ინდივიდუალური ფასები',

    // Columns & fields
    'id' => 'ID',
    'client' => 'კლიენტი',
    'product' => 'პროდუქტი',
    'actual_price' => 'ძირითადი ფასი ($)',
    'price_usd' => 'ინდივიდუალური ფასი ($)',
    'price_usd_field' => 'ფასი (USD)',
    'created_at' => 'შექმნის თარიღი',

    'attributes' => [
        'client' => 'კლიენტი',
        'product' => 'პროდუქტი',
        'price_usd' => 'ფასი (USD)',
    ],

    'messages' => [
        'client_required' => 'გთხოვთ აირჩიოთ კლიენტი.',
        'product_required' => 'გთხოვთ აირჩიოთ პროდუქტი.',
        'product_unique' => 'ამ კლიენტს ამ პროდუქტზე ინდივიდუალური ფასი უკვე აქვს.',
        'price_required' => 'გთხოვთ შეიყვანოთ ფასი.',
        'price_numeric' => 'ფასი უნდა იყოს სწორი რიცხვი.',
        'price_min' => 'ფასი არ უნდა იყოს 0-ზე ნაკლები.',
    ],

];
