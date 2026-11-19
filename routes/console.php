<?php

use Illuminate\Support\Facades\Schedule;

// Supprime les fichiers orphelins de la médiathèque et les anciens fichiers temporaires.
Schedule::command('media-library:clean --delete-orphaned --force')->dailyAt('03:00');

// Publication planifiée : rien à faire côté planificateur, les contenus « programmés »
// deviennent visibles via le scope Published (date de publication <= maintenant).
Schedule::command('queue:prune-batches')->daily();

// Base de géolocalisation des visiteurs (DB-IP City Lite) : la commande ne télécharge que si le fichier a plus de 25 jours.
Schedule::command('geoip:update')->dailyAt('04:10');
Schedule::command('model:prune')->daily();
