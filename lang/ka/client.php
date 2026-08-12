<?php

return [

    'entity' => 'კლიენტი',
    'entity_plural' => 'კლიენტები',

    // Columns & fields
    'id' => 'ID',
    'name' => 'სახელი',
    'email' => 'ელ. ფოსტა',
    'phone' => 'ტელეფონი',
    'type' => 'ტიპი',
    'client_type' => 'კლიენტის ტიპი',
    'address' => 'მისამართი',
    'personal_id' => 'პირადი ნომერი',
    'legal_id' => 'საიდენტიფიკაციო კოდი',
    'starting_balance' => 'საწყისი ბალანსი',

    'types' => [
        0 => 'ფიზიკური პირი',
        1 => 'იურიდიული პირი',
    ],

    'placeholders' => [
        'personal_id' => 'შეიყვანეთ პირადი ნომერი',
        'legal_id' => 'შეიყვანეთ საიდენტიფიკაციო კოდი',
    ],

    'hints' => [
        'client_type' => 'აირჩიეთ კლიენტის ტიპი: ფიზიკური ან იურიდიული პირი',
        'starting_balance' => 'საწყისი ბალანსი სისტემაზე გადმოსვლამდე პერიოდიდან. დადებითი = კლიენტის სასარგებლოდ, უარყოფითი = კლიენტს ერიცხება ვალი.',
    ],

    // Quick-create modal (opened from the order form)
    'modal' => [
        'title' => 'ახალი კლიენტი',
        'close' => 'დახურვა',
        'submit' => 'კლიენტის შექმნა',
    ],

    'messages' => [
        'create_failed' => 'კლიენტის შექმნა ვერ მოხერხდა: :error',
    ],

];
