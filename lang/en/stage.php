<?php

/*
| Stages module: the production stages catalogue (StageCrudController +
| StageRequest). `title` is the label users see on piece badges; `name` is the
| machine identifier stored on the piece_stage pivot.
*/

return [

    'entity' => 'stage',
    'entity_plural' => 'stages',

    // Columns & fields
    'position' => 'Order',
    'title' => 'Title',
    'name' => 'Name',
    'color' => 'Color',
    'is_universal' => 'Universal',
    'is_universal_field' => 'Universal stage',

    'yes_no' => [
        0 => 'No',
        1 => 'Yes',
    ],

    'hints' => [
        'title' => 'Display label shown to users (e.g. მოჭრა).',
        'name' => 'Machine identifier used in code lookups and as an index. Lowercase latin letters, numbers, underscores and dashes only — no spaces (e.g. frame_assembly). Changing this on an existing stage will unlink pieces already set to the old value.',
        'color' => 'Badge color for this stage.',
        'position' => 'Lower numbers appear first everywhere stages are listed.',
        'is_universal' => 'When on, this stage applies to every piece regardless of its services (e.g. მოჭრა, დასრულება).',
    ],

    'name_pattern_title' => 'Only latin letters, numbers, underscores and dashes — no spaces',

    'messages' => [
        'name_regex' => 'The name may only contain latin letters, numbers, underscores and dashes — no spaces (e.g. frame_assembly).',
    ],

];
