<?php
// flocms-reactive components (alias => class), mounted with Reactive::mount('<alias>', '<key>').
return [
    'contact' => App\Components\ContactForm::class,
    'search' => App\Components\PropertySearch::class,
    'property' => App\Components\PropertyInquiry::class,
    'development' => App\Components\DevelopmentInquiry::class,
];
