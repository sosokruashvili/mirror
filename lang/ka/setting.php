<?php

return [

    'title' => 'ზოგადი პარამეტრები',
    'subtitle' => 'აპლიკაციის საერთო პარამეტრები.',
    'general_group' => 'ზოგადი',
    'empty' => 'პარამეტრები ჯერ არ არის განსაზღვრული.',
    'save' => 'შენახვა',
    'unit_mm' => 'მმ',
    'saved' => 'პარამეტრები შენახულია.',

    // Developer tools card (dev environments only)
    'dev' => [
        'title' => 'დეველოპერის ხელსაწყოები',
        'description' => 'ამ dev ბაზის ჩანაცვლება productions-ის ახალი ასლით (:source).',
        'warning' => 'ეს <strong>წაშლის dev-ზე არსებულ ყველა მონაცემს</strong> და ვერ დაბრუნდება. სჭირდება რამდენიმე წამი.',
        'confirm' => 'წავშალოთ dev ბაზა და ჩავანაცვლოთ production-ის ასლით? ამის დაბრუნება შეუძლებელია.',
        'button' => 'ბაზის სინქრონიზაცია Production-იდან',
        'unavailable' => 'ბაზის სინქრონიზაცია ამ გარემოში ხელმისაწვდომი არ არის.',
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
