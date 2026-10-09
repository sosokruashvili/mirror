<?php

/*
| Developer database switch (topbar selector, DbConnectionController,
| partials/db-switcher.blade.php) and the dev-host banner.
*/

return [
    'label' => 'Database',
    'prod' => 'Production DB',
    'dev' => 'Development DB',
    'badge_prod' => 'PROD',
    'badge_dev' => 'DEV',
    'banner' => 'DEVELOPMENT DATABASE (:db) — changes here do NOT affect production',
    'host_badge' => 'DEV · :host',
    'host_locked' => 'This host (:host) always runs on the development database; the switch is only available on the production host.',
    'not_configured' => 'The development database is not configured (DB_DEV_DATABASE).',
    'switched_dev' => 'This session now uses the DEVELOPMENT database (:db). Nothing you do here affects production.',
    'switched_prod' => 'This session is back on the PRODUCTION database (:db).',
];
