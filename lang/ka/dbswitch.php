<?php

/*
| დეველოპერის ბაზის გადამრთველი (topbar, DbConnectionController,
| partials/db-switcher.blade.php) და dev ჰოსტის ბანერი.
*/

return [
    'label' => 'ბაზა',
    'prod' => 'Production ბაზა',
    'dev' => 'Development ბაზა',
    'badge_prod' => 'PROD',
    'badge_dev' => 'DEV',
    'banner' => 'DEVELOPMENT ბაზა (:db) — აქ შეტანილი ცვლილებები production-ზე არ აისახება',
    'host_badge' => 'DEV · :host',
    'host_locked' => 'ეს ჰოსტი (:host) ყოველთვის development ბაზაზე მუშაობს; გადართვა მხოლოდ production ჰოსტზეა შესაძლებელი.',
    'not_configured' => 'Development ბაზა არ არის კონფიგურირებული (DB_DEV_DATABASE).',
    'switched_dev' => 'ეს სესია ახლა DEVELOPMENT ბაზას იყენებს (:db). აქ შესრულებული არაფერი აისახება production-ზე.',
    'switched_prod' => 'ეს სესია ისევ PRODUCTION ბაზაზეა (:db).',
];
