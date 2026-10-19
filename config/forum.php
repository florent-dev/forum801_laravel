<?php

return [
    // Language communities: code => label, flag image (public/img/flags) and UI locale
    'communities' => [
        'en' => ['label' => 'English', 'flag' => 'gb', 'locale' => 'en'],
        'fr' => ['label' => 'Français', 'flag' => 'fr', 'locale' => 'fr'],
        'es' => ['label' => 'Español', 'flag' => 'es', 'locale' => 'es'],
        'br' => ['label' => 'Português', 'flag' => 'br', 'locale' => 'pt_BR'],
        'de' => ['label' => 'Deutsch', 'flag' => 'de', 'locale' => 'de'],
        'pl' => ['label' => 'Polski', 'flag' => 'pl', 'locale' => 'pl'],
        'ru' => ['label' => 'Русский', 'flag' => 'ru', 'locale' => 'ru'],
        'tr' => ['label' => 'Türkçe', 'flag' => 'tr', 'locale' => 'tr'],
        'nl' => ['label' => 'Nederlands', 'flag' => 'nl', 'locale' => 'nl'],
        'it' => ['label' => 'Italiano', 'flag' => 'it', 'locale' => 'it'],
        'hu' => ['label' => 'Magyar', 'flag' => 'hu', 'locale' => 'hu'],
        'ro' => ['label' => 'Română', 'flag' => 'ro', 'locale' => 'ro'],
    ],

    'default_community' => 'en',

    // Interface languages, independent from communities: locale => label and flag
    'locales' => [
        'en' => ['label' => 'English', 'flag' => 'gb'],
        'fr' => ['label' => 'Français', 'flag' => 'fr'],
        'es' => ['label' => 'Español', 'flag' => 'es'],
        'pt_BR' => ['label' => 'Português (Brasil)', 'flag' => 'br'],
        'de' => ['label' => 'Deutsch', 'flag' => 'de'],
        'pl' => ['label' => 'Polski', 'flag' => 'pl'],
        'ru' => ['label' => 'Русский', 'flag' => 'ru'],
        'tr' => ['label' => 'Türkçe', 'flag' => 'tr'],
        'nl' => ['label' => 'Nederlands', 'flag' => 'nl'],
        'it' => ['label' => 'Italiano', 'flag' => 'it'],
        'hu' => ['label' => 'Magyar', 'flag' => 'hu'],
        'ro' => ['label' => 'Română', 'flag' => 'ro'],
    ],

    'default_locale' => 'en',

    // Image captcha on registration (self-hosted, no third-party service)
    'captcha' => env('FORUM_CAPTCHA', true),

    // How many guilds a user can belong to
    'max_guilds_per_user' => env('FORUM_MAX_GUILDS', 5),

    // Days to keep IP addresses and security events before they are purged
    'security_retention_days' => env('FORUM_SECURITY_RETENTION_DAYS', 180),
];
