<?php

/*
|--------------------------------------------------------------------------
| Supported Locales
|--------------------------------------------------------------------------
|
| Every locale the admin panel can be displayed in. The array key is the
| locale code used by Laravel (app()->setLocale()) and by the lang/ folders.
|
|   native  - language name in its own language (shown in the switcher)
|   short   - 2-letter badge shown in the collapsed switcher button
|   regional- used for Carbon/number formatting where a region is needed
|   dir     - text direction, fed to backpack.ui.html_direction
|
*/

return [

    'supported' => [
        'ka' => [
            'native' => 'ქართული',
            'short' => 'ქა',
            'regional' => 'ka_GE',
            'dir' => 'ltr',
        ],
        'en' => [
            'native' => 'English',
            'short' => 'EN',
            'regional' => 'en_US',
            'dir' => 'ltr',
        ],
    ],

];
