<?php

namespace App\Support;

/**
 * Textes que les scripts du site affichent eux-mêmes (resources/js, fonction t()). Le tableau est publié dans la page sous
 * window.uazI18n : phrase française → texte de la langue courante. Toute phrase passée à t() doit figurer dans KEYS
 * (l'inventaire des traductions, php artisan i18n:check, la lit aussi).
 */
final class JsStrings
{
    public const KEYS = [
        // Lettre d'information
        'Trop de tentatives. Réessayez dans une minute.',
        'Une erreur est survenue. Réessayez dans quelques instants.',
        'Connexion impossible. Vérifiez votre réseau et réessayez.',
        'Adresse e-mail invalide.',
        // Recherche
        'La recherche est momentanément indisponible. Réessayez dans un instant.',
        // Frais académiques
        'Copié',
        'Sélectionnez le numéro',
        'Année complète',
        'Semestre :number',
        'Cumul des deux semestres',
        'Seul le semestre :number figure sur le tableau officiel.',
        'Répartition : :parts',
        'Licence',
        'Master',
        'Interne',
        'Externe',
        // Yearbook
        'Page :page / :total',
        'Pages :from–:to / :total',
        // Équipe
        'Profil',
        // Horaires de la bibliothèque
        'La bibliothèque est ouverte',
        'La bibliothèque est fermée',
        'Le prêt et la prolongation sont ouverts',
        'Le prêt et la prolongation sont fermés',
        'ouvre',
        'reprennent',
        'demain',
        'dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi',
        'Horaires indisponibles',
        ':status (heure de Madagascar)',
        'Horaires du :day non communiqués',
        ':status · jusqu\'à :time',
        ':status · reprend à :time',
        ':status · :verb :when à :time',
    ];

    /** @return array<string, string> */
    public static function all(): array
    {
        return collect(self::KEYS)->mapWithKeys(fn (string $key) => [$key => __($key)])->all();
    }
}
