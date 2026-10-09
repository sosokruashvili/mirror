<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cabinet hostname
    |--------------------------------------------------------------------------
    |
    | Client pages are served only on this host (client.mirrorgallery.ge).
    | The admin panel stays on APP_URL. The two never share a session cookie.
    |
    */

    'host' => env('CABINET_HOST', 'client.mirrorgallery.ge'),

];
