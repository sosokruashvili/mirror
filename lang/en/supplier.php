<?php

/*
| Suppliers module: SupplierCrudController columns/fields and the
| SupplierRequest validation attribute names.
*/

return [

    'entity' => 'supplier',
    'entity_plural' => 'suppliers',

    // Columns & fields
    'id' => 'ID',
    'name' => 'Name',
    'description' => 'Description',
    'email' => 'Email',
    'phone' => 'Phone',
    'address' => 'Address',
    'legal_id' => 'Legal ID',
    'expense_categories' => 'Expense categories',

    'hints' => [
        'expense_categories' => 'Select one or more expense categories this supplier covers.',
    ],

    // Validation attribute names (interpolated into :attribute messages, so
    // lowercase in English the way Laravel's own messages read).
    'attributes' => [
        'name' => 'name',
        'description' => 'description',
        'email' => 'email',
        'address' => 'address',
        'phone' => 'phone',
        'legal_id' => 'legal ID',
        'expense_categories' => 'expense categories',
    ],

];
