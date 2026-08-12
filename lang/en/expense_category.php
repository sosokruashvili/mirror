<?php

/*
| Expense Categories module (ExpenseCategoryCrudController +
| ExpenseCategoryRequest). The category names themselves are DB rows, so only
| the chrome around them is translated here.
*/

return [

    'entity' => 'expense category',
    'entity_plural' => 'expense categories',

    // Columns & fields
    'name' => 'Name',
    'parent' => 'Parent',
    'depth' => 'Depth',

    'hints' => [
        'parent' => 'Leave empty for a top-level category. You can also nest via Reorder.',
    ],

    // Validation attribute names (interpolated into :attribute messages, so
    // lowercase in English the way Laravel's own messages read).
    'attributes' => [
        'name' => 'name',
        'parent' => 'parent',
    ],

    'messages' => [
        'self_nesting' => 'A category cannot be nested under itself or its descendants.',
        'has_children' => 'Cannot delete a category that has child categories. Move or delete children first.',
        'in_use' => 'Cannot delete a category that is used by expenses.',
    ],

];
