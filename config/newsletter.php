<?php

return [
    /*
     * Cadence d'envoi : délai, en secondes, entre deux e-mails d'une même lettre. Les hébergeurs limitent le nombre de messages par heure
     * (voir docs/DEPLOIEMENT.md) : une cadence régulière évite qu'une lettre soit bloquée ou classée en courrier indésirable.
     * Ignoré quand la file d'attente est « sync » (développement : tout part immédiatement).
     */
    'seconds_between_messages' => (int) env('NEWSLETTER_SECONDS_BETWEEN', 2),

    /** Nombre de tentatives par abonné avant de noter l'échec (la file attend 1 puis 5 minutes entre deux essais). */
    'tries' => 3,
];
