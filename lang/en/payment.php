<?php

/*
| Payment types and methods. Keys are the values stored in payments.type /
| payments.method, so they must never change - only the labels are translated.
*/

return [

    'types' => [
        'Order' => 'Order',
        'Debt' => 'Debt',
    ],

    'methods' => [
        'Cash' => 'Cash',
        'Transfer' => 'Transfer',
        'Terminal' => 'Terminal',
        'PM Transfer' => 'PM Transfer',
    ],

];
