<?php

/*
|--------------------------------------------------------------------------
| Database sync (prod -> dev)
|--------------------------------------------------------------------------
|
| Defines the PRODUCTION database that the `db:sync-from-prod` command copies
| INTO the current (dev) database. Prod and dev live on the same PostgreSQL
| server sharing the same role, so the defaults below only differ from the
| app's own connection by database name.
|
| SAFETY: the command always writes into the CURRENT connection's database and
| refuses to run when that database equals the source below. On prod the app's
| database IS the source ('mirror'), so the feature auto-disables there.
|
*/

return [
    'source' => [
        'host' => env('DB_SYNC_SOURCE_HOST', env('DB_HOST', '127.0.0.1')),
        'port' => env('DB_SYNC_SOURCE_PORT', env('DB_PORT', '5432')),
        'database' => env('DB_SYNC_SOURCE_DATABASE', 'mirror'),
        'username' => env('DB_SYNC_SOURCE_USERNAME', env('DB_USERNAME', 'mirror')),
        'password' => env('DB_SYNC_SOURCE_PASSWORD', env('DB_PASSWORD', '')),
    ],
];
