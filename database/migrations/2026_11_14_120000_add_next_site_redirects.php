<?php

use App\Models\Redirect;
use Illuminate\Database\Migrations\Migration;

/**
 * Redirections 301 depuis les adresses du site Next.js (en ligne du 4 novembre à la mise en production de ce site) vers leurs pages
 * équivalentes : liens déjà partagés ou indexés. Une redirection déjà saisie dans le back-office pour la même adresse est conservée.
 * Le préfixe « /fr » de l'ancien site est retiré par Nginx (voir scripts/provision-prod.sh).
 */
return new class extends Migration
{
    private const MAP = [
        '/admission/licence' => '/admissions/licence',
        '/admission/master' => '/admissions/master',
        '/admission/frais' => '/admissions/frais-academiques',
        '/formations/communication' => '/formations/licence-en-communication',
        '/formations/droit' => '/formations/licence-en-droit',
        '/formations/etudes-anglophones' => '/formations/licence-en-etudes-anglophones',
        '/formations/gestion' => '/formations/licence-en-gestion',
        '/formations/informatique' => '/formations/licence-en-informatique',
        '/formations/sciences-infirmieres' => '/formations/licence-en-sciences-infirmieres',
        '/formations/theologie' => '/formations/licence-en-theologie',
        '/universite' => '/a-propos',
        '/universite/equipe' => '/equipe',
        '/actualites/a-la-rencontre-des-futurs-etudiants' => '/actualites/luaz-a-la-rencontre-des-futurs-etudiants',
        '/actualites/appel-a-candidature-sciences-infirmieres' => '/actualites/appel-a-candidature-departement-sciences-infirmieres',
        '/actualites/bilan-concours-art-oratoire' => '/actualites/lart-de-la-parole-et-de-lesprit-a-lhonneur-le-service-academique-dresse-le-bilan-des-concours-de-luaz',
        '/actualites/culte-ouverture-annee-academique-2026-2027' => '/actualites/culte-douverture-de-lannee-academique-2026-2027-une-nouvelle-page-souvre-a-luaz',
        '/actualites/inauguration-centre-medical-zurcher' => '/actualites/inauguration-du-centre-medical-zurcher',
        '/actualites/master-en-ligne-ouverture-annee-academique' => '/actualites/master-en-ligne-luaz-ouvre-officiellement-son-annee-academique',
        '/actualites/ouverture-master-communication' => '/actualites/luaz-ouvre-son-master-en-communication-une-nouvelle-filiere',
        '/actualites/recrutement-secretaire-academique' => '/actualites/universite-adventiste-zurcher-recherche-son-sa-future-secretaire-academique',
        '/actualites/sortie-team-building-green-palm' => '/actualites/sortie-et-team-building-a-green-palm',
    ];

    public function up(): void
    {
        foreach (self::MAP as $from => $to) {
            Redirect::firstOrCreate(
                ['from_path' => Redirect::normalize($from)],
                ['to_path' => $to, 'status_code' => 301, 'is_active' => true, 'note' => 'Ancienne adresse du site Next.js'],
            );
        }
    }

    public function down(): void
    {
        Redirect::query()->whereIn('from_path', array_keys(self::MAP))->where('note', 'Ancienne adresse du site Next.js')->delete();
    }
};
