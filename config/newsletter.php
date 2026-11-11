<?php

return [
    /*
     * Cadence d'envoi : délai, en secondes, entre deux e-mails d'une même lettre. Les hébergeurs limitent le nombre de messages par heure
     * (voir docs/DEPLOIEMENT.md) : une cadence régulière évite qu'une lettre soit bloquée ou classée en courrier indésirable.
     * Ignoré quand la file d'attente est « sync » (développement : tout part immédiatement).
     */
    'seconds_between_messages' => (int) env('NEWSLETTER_SECONDS_BETWEEN', 2),

    /** Expéditeur des lettres : une adresse dédiée, distincte de celle des messages du site (réponses aux formulaires, confirmations). */
    'from' => [
        'address' => env('NEWSLETTER_FROM_ADDRESS', 'newsletter@zurcher.edu.mg'),
        'name' => env('NEWSLETTER_FROM_NAME', 'Université Adventiste Zurcher'),
    ],

    /**
     * Images jointes au message (au lieu d'être chargées depuis le site). Indispensable quand le site n'est pas public (en local, les images
     * pointeraient vers « localhost », invisible pour le destinataire). Vide = automatique : jointes seulement si l'adresse du site est locale
     * (localhost, 127.0.0.1, .test, .local). « true » pour toujours les joindre, « false » pour ne jamais le faire.
     */
    'embed_images' => env('NEWSLETTER_EMBED_IMAGES'),

    /** Nombre de tentatives par abonné avant de noter l'échec (la file attend 1 puis 5 minutes entre deux essais). */
    'tries' => 3,
];
