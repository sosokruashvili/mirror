<?php

/*
| Machines a glass can break on, picked in the team's "broke" dialog.
| Machines are switched off rather than deleted, because old breaks reference them.
*/

return [

    'entity' => 'machine',
    'entity_plural' => 'machines',

    'name' => 'Name',
    'position' => 'Order',
    'position_hint' => 'Order in the "broke" dialog list.',
    'requires_comment' => 'Comment required',
    'requires_comment_hint' => 'E.g. "Other": the team must write a comment when picking this machine.',
    'is_active' => 'Active',
    'is_active_hint' => 'An inactive machine is hidden from the "broke" dialog but stays on old records.',
    'breaks' => 'Breaks',
    'yes' => 'Yes',
    'no' => 'No',

];
