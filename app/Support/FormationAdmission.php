<?php

namespace App\Support;

/**
 * Conditions d'admission d'une licence (matières du test et séries de Baccalauréat), tirées de config/admissions.php
 * (texte du site zurcher.edu.mg, page « Licence »). Partagé par le seeder et la migration de rattrapage.
 */
final class FormationAdmission
{
    /** HTML de la rubrique « Conditions d'admission » d'une mention de Licence, ou null si la mention est inconnue. */
    public static function html(string $mention): ?string
    {
        $row = collect(config('admissions.levels.licence.mentions'))->firstWhere('formation', $mention);

        if (! $row) {
            return null;
        }

        return '<p><strong>Matières du test d\'admission :</strong> '.e($row['test']).'.</p>'
            .'<p><strong>Séries de Baccalauréat acceptées :</strong> '.e($row['series']).'.</p>'
            .'<p>Ces conditions concernent les étudiants malgaches ; le dossier à fournir et les dates figurent sur la page d\'admission en Licence.</p>';
    }
}
