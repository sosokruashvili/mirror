<?php

return [

    'entity' => 'ხარჯი-შესყიდვა',
    'entity_plural' => 'ხარჯები და შესყიდვები',

    // Columns & fields
    'id' => 'ID',
    'type' => 'ტიპი',
    'category' => 'კატეგორია',
    'supplier' => 'მომწოდებელი',
    'product' => 'პროდუქტი',
    'price_usd' => 'შესყიდვის ფასი ($)',
    'price_usd_field' => 'შესყიდვის ფასი (USD)',
    'amount_gel' => 'თანხა (₾)',
    'amount_gel_field' => 'თანხა (₾)',
    'credit' => 'დავალიანება (₾)',
    'credit_field' => 'დავალიანება (₾)',
    'payment_progress' => 'გადახდილი (%)',
    'description' => 'აღწერა',
    'file' => 'ფაილი',
    'expense_date' => 'თარიღი',

    'filters' => [
        'date_range' => 'პერიოდი',
    ],

    // Tooltip on the paid/credit progress bar
    'progress_title' => 'გადახდილი :paid ₾ · დავალიანება :credit ₾ · ჯამი :total ₾',

    'placeholders' => [
        'product' => 'მოძებნეთ და აირჩიეთ პროდუქტი',
        'price_usd' => 'აირჩიეთ მომწოდებელი და პროდუქტი',
    ],

    'hints' => [
        'supplier' => 'მხოლოდ არჩეულ კატეგორიაზე მიბმული მომწოდებლები.',
        'product' => 'მხოლოდ საწარმოო შესყიდვებისთვის.',
        'price_usd' => 'ავტომატურად ივსება მომწოდებლების ფასებიდან, როცა მომწოდებელი და პროდუქტი ემთხვევა.',
        'amount_gel' => 'ხარჯის სრული თანხა.',
        'credit' => 'სრული თანხიდან დავალიანების ოდენობა (გადაუხდელი ნაწილი).',
        'file' => 'დაშვებული ტიპები: PDF, PNG, JPEG, JPG',
    ],

    'category_has_children' => ':name (აქვს ქვეკატეგორიები — აირჩიეთ ბოლო დონე)',

    // Stats widget above the list
    'stats' => [
        'total_amount' => 'ჯამური თანხა',
        'total_credit' => 'დავალიანება სულ',
        'total_cash' => 'ნაღდით გადახდილი',
        'total_transfer' => 'გადარიცხვით გადახდილი',
        'total_pm_transfer' => 'PM გადარიცხვით გადახდილი',
    ],

    'attributes' => [
        'type' => 'ტიპი',
        'category' => 'კატეგორია',
        'supplier' => 'მომწოდებელი',
        'product' => 'პროდუქტი',
        'price_usd' => 'შესყიდვის ფასი (USD)',
        'amount_gel' => 'თანხა (₾)',
        'credit' => 'დავალიანება',
        'description' => 'აღწერა',
        'file' => 'ფაილი',
        'expense_date' => 'ხარჯის თარიღი',
    ],

    'messages' => [
        'category_not_leaf' => 'გთხოვთ აირჩიოთ ბოლო დონის კატეგორია (ისეთი, რომელსაც ქვეკატეგორიები არ აქვს).',
        'supplier_not_linked' => 'არჩეული მომწოდებელი ამ კატეგორიას არ უკავშირდება.',
        'credit_exceeds_amount' => 'დავალიანება არ უნდა აღემატებოდეს სრულ თანხას.',
    ],

];
