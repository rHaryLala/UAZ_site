<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Langues du site
    |--------------------------------------------------------------------------
    |
    | Le français est la langue par défaut (URL sans préfixe) ; l'anglais et le malagasy vivent sous /en et /mg.
    | « og » : valeur de og:locale. Voir docs/I18N.md.
    |
    */
    'locales' => [
        'fr' => ['name' => 'Français', 'short' => 'FR', 'og' => 'fr_FR'],
        'en' => ['name' => 'English', 'short' => 'EN', 'og' => 'en_US'],
        'mg' => ['name' => 'Malagasy', 'short' => 'MG', 'og' => 'mg_MG'],
    ],
    'default_locale' => 'fr',

    /*
    | Année de fondation affichée dans la barre du haut. TODO-CONTENU : la maquette dit 1995 dans le PDF et 1996
    | dans l'en-tête de la capture ; à confirmer par l'UAZ (voir docs/TODO-CONTENU.md).
    */
    'founded_year' => (int) env('UAZ_FOUNDED_YEAR', 1995),

    /*
    | Météo du campus (pied de page) via Open-Meteo. Coordonnées approximatives : Sambaina, commune d'Antsirabe II
    | (GeoNames) ; suffisant pour la météo, à ne pas utiliser pour placer un repère exact sur une carte.
    */
    'weather' => [
        'enabled' => (bool) env('UAZ_WEATHER', true),
        'latitude' => -19.61667,
        'longitude' => 47.13333,
    ],

    /*
    | Carte Google Maps du campus (lien de l'adresse dans le pied de page).
    */
    'map_url' => env('UAZ_MAP_URL', 'https://maps.app.goo.gl/stjXGTSu6bDFScS69'),

    /*
    | Yearbooks numériques : pages WebP dans public/books/yearbook/{année}/ (page-001.webp…, thumbs/ pour les miniatures).
    | Générées à partir du PDF d'origine ; le PDF reste téléchargeable depuis son adresse d'origine.
    */
    'yearbooks' => [
        '2025' => [
            'title' => 'Yearbook 2025',
            'subtitle' => 'Clic',
            'pages' => 44,
            'pdf' => 'https://zurcher.edu.mg/wp-content/uploads/2025/12/Yearbook-2025-.pdf',
        ],
    ],

    /*
    | Don en ligne : site qui reçoit les dons (par défaut funding.zurcher.edu.mg). Tant qu'il est défini, « Donner » et « Soutenir l'UAZ »
    | (menus, accueil, /soutenir et /soutenir/{projet}) envoient directement vers lui, sans passer par une page du site.
    | Vide (UAZ_DONATION_URL=) : la page « Soutenir l'UAZ » reprend du service, propose de contacter l'équipe et annonce que le don en ligne arrive bientôt.
    */
    'donation_url' => env('UAZ_DONATION_URL', 'https://funding.zurcher.edu.mg'),

    /*
    | Contacts de l'appel aux dons (document « Appel aux dons - UAZ »).
    */
    'donation_contacts' => [
        'phones' => ['038 07 731 18', '034 08 725 37'],
        'emails' => ['uaz.rector@zurcher.edu.mg', 'financedir@zurcher.edu.mg'],
    ],

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
            // Héros plein écran : pleine largeur de l'original et compression douce (la photo est vue en grand, parfois sur écran dense).
            'hero' => ['width' => 2560, 'responsive' => true, 'webp_quality' => 90, 'avif' => false],
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
