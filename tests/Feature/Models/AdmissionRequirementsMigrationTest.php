<?php

use App\Models\Formation;
use App\Models\TeamMember;
use App\Support\FormationAdmission;
use Database\Seeders\StructureSeeder;

function admissionMigration(): object
{
    return require database_path('migrations/2026_11_10_100000_fill_licence_admission_requirements.php');
}

it('seeds each licence with the admission test subjects and Baccalauréat series of its mention', function () {
    $this->seed(StructureSeeder::class);

    $gestion = Formation::where('title', 'Licence en Gestion')->first();

    expect($gestion->admission_requirements)
        ->toContain('Français, Mathématiques')
        ->toContain('D, C, G2, G3, A2')
        ->not->toContain('TODO-CONTENU')
        ->and(Formation::where('admission_requirements', 'like', '%TODO-CONTENU%')->count())->toBe(0);
});

it('replaces only the admission conditions still marked TODO-CONTENU, keeps edited ones, and is idempotent', function () {
    $this->seed(StructureSeeder::class);
    $droit = Formation::where('title', 'Licence en Droit')->first();
    $theologie = Formation::where('title', 'Licence en Théologie')->first();

    // site installé avant la migration : un texte provisoire, un texte déjà saisi par la rédaction
    $droit->update(['admission_requirements' => '<p>Admission après le baccalauréat. TODO-CONTENU : conditions détaillées.</p>']);
    $theologie->update(['admission_requirements' => '<p>Texte rédigé à la main.</p>']);

    admissionMigration()->up();
    admissionMigration()->up();

    expect($droit->fresh()->admission_requirements)->toBe(FormationAdmission::html('Droit'))
        ->and($droit->fresh()->admission_requirements)->toContain('Français, connaissance générale')
        ->and($theologie->fresh()->admission_requirements)->toBe('<p>Texte rédigé à la main.</p>');
});

it('gives the rector his quote once, without overwriting one already entered, and can be rolled back', function () {
    $rector = TeamMember::factory()->create(['email' => 'rector@zurcher.edu.mg', 'quote' => null]);
    $other = TeamMember::factory()->create(['email' => 'autre@zurcher.edu.mg', 'quote' => null]);

    admissionMigration()->up();
    expect($rector->fresh()->quote)->toBe('Préparer les leaders de demain est une noble et importante mission.')
        ->and($other->fresh()->quote)->toBeNull();

    $rector->update(['quote' => 'Citation saisie par la rédaction.']);
    admissionMigration()->up();
    expect($rector->fresh()->quote)->toBe('Citation saisie par la rédaction.');

    $rector->update(['quote' => 'Préparer les leaders de demain est une noble et importante mission.']);
    admissionMigration()->down();
    expect($rector->fresh()->quote)->toBeNull();
});

it('returns nothing for a mention that has no admission data', function () {
    expect(FormationAdmission::html('Éducation'))->toBeNull()
        ->and(FormationAdmission::html('Mention inconnue'))->toBeNull();
});
