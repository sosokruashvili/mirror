<?php

return [

    'entity' => 'მომწოდებლის ფასი',
    'entity_plural' => 'მომწოდებლების ფასები',

    // Columns & fields
    'id' => 'ID',
    'product' => 'პროდუქტი',
    'supplier' => 'მომწოდებელი',
    'price_usd' => 'შესყიდვის ფასი ($)',
    'price_usd_field' => 'შესყიდვის ფასი (USD)',
    'sale_price' => 'გასაყიდი ფასი ($)',
    'last_updated' => 'ბოლო განახლება',

    'hints' => [
        'price_usd' => 'რა ფასად გვყიდის ეს მომწოდებელი პროდუქტის ერთ ერთეულს.',
    ],

    'attributes' => [
        'supplier' => 'მომწოდებელი',
        'product' => 'პროდუქტი',
        'price_usd' => 'შესყიდვის ფასი (USD)',
    ],

    'messages' => [
        'supplier_required' => 'გთხოვთ აირჩიოთ მომწოდებელი.',
        'product_required' => 'გთხოვთ აირჩიოთ პროდუქტი.',
        'product_unique' => 'ამ მომწოდებელს ამ პროდუქტზე ფასი უკვე აქვს.',
        'price_required' => 'გთხოვთ შეიყვანოთ შესყიდვის ფასი.',
        'price_numeric' => 'შესყიდვის ფასი უნდა იყოს სწორი რიცხვი.',
        'price_min' => 'შესყიდვის ფასი არ უნდა იყოს 0-ზე ნაკლები.',
    ],

];
