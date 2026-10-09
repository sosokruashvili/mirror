<?php

return [

    'title' => 'ზოგადი პარამეტრები',
    'subtitle' => 'აპლიკაციის საერთო პარამეტრები.',
    'general_group' => 'ზოგადი',
    'empty' => 'პარამეტრები ჯერ არ არის განსაზღვრული.',
    'save' => 'შენახვა',
    'unit_mm' => 'მმ',
    'saved' => 'პარამეტრები შენახულია.',

    // Developer tools card (developer on the development DB only)
    'dev' => [
        'title' => 'დეველოპერის ხელსაწყოები',
        'description' => 'Development ბაზის :target ჩანაცვლება production-ის ახალი ასლით (:source).',
        'warning' => 'ეს <strong>წაშლის :target-ში არსებულ ყველა მონაცემს</strong> (ჯერ იქმნება სარეზერვო ასლი storage/app/db-backups-ში). Production არ იცვლება. სჭირდება რამდენიმე წამი.',
        'confirm' => 'წავშალოთ development ბაზა ":target" და ჩავანაცვლოთ production-ის ასლით?',
        'type_to_confirm' => 'დასადასტურებლად აკრიფეთ :target',
        'confirm_mismatch' => 'დადასტურება არ ემთხვევა development ბაზის სახელს (:db). არაფერი შეცვლილა.',
        'button' => 'Production → Development კოპირება',
        'unavailable' => 'ბაზის სინქრონიზაცია ხელმისაწვდომია მხოლოდ დეველოპერისთვის development ბაზაზე მუშაობისას.',
        'synced' => 'ბაზა სინქრონიზდა production-იდან.',
        'failed' => 'ბაზის სინქრონიზაცია ვერ მოხერხდა: :error',
        'no_output' => 'პასუხი არ არის — იხილეთ ლოგი დეტალებისთვის',
    ],

    // Rows of the settings table
    'groups' => [
        'Production' => 'წარმოება',
    ],

    'labels' => [
        'cutting_size' => 'ჭრის ზომა',
    ],

    'descriptions' => [
        'cutting_size' => 'ჭრის ზომა მილიმეტრებში (მმ).',
    ],

];
