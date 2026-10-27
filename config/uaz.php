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
    | Année de fondation affichée dans la barre du haut. TODO-CONTENU : la maquette dit 1995 dans le PDF et 1996
    | dans l'en-tête de la capture ; à confirmer par l'UAZ (voir docs/TODO-CONTENU.md).
    */
    'founded_year' => (int) env('UAZ_FOUNDED_YEAR', 1995),

    /*
    | Don en ligne : adresse de la page de paiement (Stripe Checkout, PayPal, mobile money, site de la fondation…).
    | Vide : la page « Soutenir l'UAZ » propose de contacter l'équipe et annonce que le don en ligne arrive bientôt.
    */
    'donation_url' => env('UAZ_DONATION_URL'),

    /*
    | Inscription en ligne (bureau académique) : cible des boutons « S'inscrire » et « Candidater ».
    */
    'registration_url' => env('UAZ_REGISTRATION_URL', 'https://registrar.zurcher.edu.mg/#pre-inscription'),

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
        // Délai minimal (secondes) entre l'affichage et l'envoi : plus rapide = robot, message écarté en silence.
        'min_fill_seconds' => 3,
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
