<?php

/**
 * One-off import of the 14-user Excel table (2026-07-10).
 * Matches existing users by email, then phone; creates missing ones.
 * Stage "roles" from the Excel are synced as direct Spatie permissions.
 *
 * Run with: php artisan tinker database/scripts/import_users_2026_07_10.php
 */

use App\Models\User;
use Illuminate\Support\Str;

$rows = [
    ['name' => 'გიგა ბერუაშვილი', 'phone' => '+995598313158', 'email' => 'mirrorgallery01@gmail.com', 'permissions' => ['cutting', 'processing']],
    ['name' => 'ზაზა ზედგენიძე', 'phone' => '+995595766060', 'email' => 'zazazedgenizegallery@gmail.com', 'permissions' => ['processing']],
    ['name' => 'დათო ლაზრიშვილი', 'phone' => '+995571064179', 'email' => 'datolazrishviligallery@gmail.com', 'permissions' => ['cutting-drilling', 'tempering']],
    ['name' => 'ომიკო თვაური', 'phone' => '+995598349223', 'email' => 'omartvaurigallery@gmail.com', 'permissions' => ['cutting', 'processing']],
    // NOTE: existing user #12 (giorgibobokhidze48@gmail.com) may be the same person, but
    // name/phone/email all differ — creating as a new user, flagged for manual review.
    ['name' => 'გიორგი ბობოხიძე', 'phone' => '+995574032862', 'email' => 'giorgiboboxidzegallery@gmail.com', 'permissions' => ['processing', 'finishing']],
    ['name' => 'გიო ხორბალაძე', 'phone' => '+995597182128', 'email' => 'giorgixorbaladzegallery@gmail.com', 'permissions' => ['cutting', 'assembly']],
    // NOTE: existing user #7 "გიორგი კავთარაძე" (gialoo1991@...) might be the same person,
    // but the surname, phone and email all differ — creating as a new user to be safe.
    ['name' => 'გიორგი კავთელაძე', 'phone' => '+995597323022', 'email' => 'giorgikavteladzegallery@gmail.com', 'permissions' => ['cutting', 'processing']],
    ['name' => 'სანდრო არხოშაშვილი', 'phone' => '+995599749521', 'email' => 'sandroarxoshashviligallery@gmail.com', 'permissions' => ['processing', 'assembly']],
    ['name' => 'ავთო არხოშაშვილი', 'phone' => '+995595406017', 'email' => 'avtoarxoshashviligallery@gmail.com', 'permissions' => ['curing', 'processing']],
    ['name' => 'ამირან ბერიანიძე', 'phone' => '+995579321194', 'email' => 'amiranberianidzegallery@gmail.com', 'permissions' => ['curing', 'assembly']],
    ['name' => 'ელისაბედ გიგაშვილი', 'phone' => '+995599062206', 'email' => 'eliso_gigashvili@yahoo.com', 'permissions' => []],
    ['name' => 'ქეთი ნარიმანიძე', 'phone' => '+995599616461', 'email' => 'knarimanidze24@gmail.com', 'permissions' => []],
    ['name' => 'ელზა ბერიკაშვილი', 'phone' => '+995593132624', 'email' => 'berikashvilielza5@gmail.com', 'permissions' => []],
    ['name' => 'თამარ ნორაკიძე', 'phone' => '+995568605109', 'email' => 'norakidzetamari@gmail.com', 'permissions' => []],
];

$created = [];

foreach ($rows as $row) {
    $user = User::where('email', $row['email'])->first();
    $user ??= User::where('phone', $row['phone'])->first();

    if ($user) {
        $user->update([
            'name' => $row['name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
        ]);
        $action = 'updated #' . $user->id;
    } else {
        $password = Str::random(10);
        $user = User::create([
            'name' => $row['name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'password' => $password,
        ]);
        $created[$row['email']] = $password;
        $action = 'CREATED #' . $user->id;
    }

    $user->syncPermissions($row['permissions']);

    echo str_pad($action, 14) . ' | ' . $row['name'] . ' | ' . $row['email']
        . ' | perms: ' . (implode(', ', $row['permissions']) ?: '-') . PHP_EOL;
}

if ($created) {
    echo PHP_EOL . 'Generated passwords for NEW users (share securely, ask them to change):' . PHP_EOL;
    foreach ($created as $email => $password) {
        echo $email . ' => ' . $password . PHP_EOL;
    }
}
