<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Langues du site
    |--------------------------------------------------------------------------
    |
    | Le site est en français. L'anglais viendra plus tard : il suffira d'ajouter
    | 'en' ici, de traduire lang/en et d'activer les préfixes de route (voir
    | docs/ARCHITECTURE.md, section Internationalisation).
    |
    */
    'locales' => ['fr'],
    'default_locale' => 'fr',

    /*
    |--------------------------------------------------------------------------
    | Médiathèque
    |--------------------------------------------------------------------------
    */
    'media' => [
        'max_file_size_kb' => (int) env('MEDIA_MAX_FILE_SIZE_KB', 10240),
        'accepted_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/avif',
            'application/pdf',
        ],
        // Largeur max. de l'original conservé (compression automatique à l'envoi).
        'original_max_width' => 2560,
        // Génère aussi des conversions AVIF quand GD le permet (plus lent à générer).
        'avif' => (bool) env('MEDIA_AVIF', true),
        'conversions' => [
            'thumb' => ['width' => 400, 'responsive' => false],
            'card' => ['width' => 960, 'responsive' => true],
            'hero' => ['width' => 2000, 'responsive' => true],
        ],
        'webp_quality' => 80,
        'avif_quality' => 55,
    ],

    /*
    |--------------------------------------------------------------------------
    | Compte super-admin du seeder (développement)
    |--------------------------------------------------------------------------
    */
    'seed_admin' => [
        'name' => env('SEED_ADMIN_NAME', 'Administrateur UAZ'),
        'email' => env('SEED_ADMIN_EMAIL', 'admin@uaz.test'),
        'password' => env('SEED_ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Contact
    |--------------------------------------------------------------------------
    */
    'contact' => [
        'recipient' => env('CONTACT_RECIPIENT'),
        'rate_limit_per_minute' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Import WordPress
    |--------------------------------------------------------------------------
    */
    'wordpress' => [
        'url' => env('WORDPRESS_URL', 'https://zurcher.edu.mg'),
        'delay_ms' => 600,
        'user_agent' => 'UAZ-importer/1.0 (lecture seule)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Fonctionnalités
    |--------------------------------------------------------------------------
    */
    'design_system_enabled' => env('APP_ENV') !== 'production',

];
