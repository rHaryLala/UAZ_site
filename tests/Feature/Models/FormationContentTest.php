<?php

use App\Models\Formation;
use App\Models\Redirect;
use App\Models\Testimonial;
use App\Support\FormationContent;
use App\Support\Locales;

function runMigration(string $file): void
{
    (require database_path("migrations/$file"))->up();
}

it('describes every licence mention in French, English and Malagasy', function () {
    foreach (['Théologie', 'Communication', 'Études anglophones', 'Gestion', 'Droit', 'Sciences infirmières', 'Informatique'] as $mention) {
        foreach (['fr', 'en', 'mg'] as $locale) {
            expect(FormationContent::description($mention, $locale))->toContain('<h3>')->toContain('<li>')
                ->and(FormationContent::careers($mention, $locale))->toContain('<li>')
                ->and(FormationContent::description($mention, $locale).FormationContent::careers($mention, $locale))->not->toContain('TODO-CONTENU');
        }
    }

    expect(FormationContent::description('Inconnue'))->toBeNull()->and(FormationContent::careers('Inconnue'))->toBeNull();
});

it('shows the Master only for the mentions that have one', function () {
    expect(FormationContent::description('Communication'))->toContain('Master')->toContain('2 ans · 120 crédits ECTS')
        ->and(FormationContent::description('Droit'))->not->toContain('Master')
        ->and(FormationContent::description('Communication', 'en'))->toContain('Master&#039;s degree');
});

it('replaces the waiting text of the formations with the real presentation and careers, in three languages', function () {
    $formation = Formation::factory()->create([
        'mention' => 'Communication',
        'description' => '<p>TODO-CONTENU : présentation détaillée de la formation.</p>',
        'career_prospects' => '<p>TODO-CONTENU : débouchés professionnels.</p>',
    ]);

    runMigration('2026_11_14_100000_fill_formation_presentations.php');

    $formation->refresh();
    expect($formation->getRawOriginal('description'))->toContain('Informer, raconter et convaincre')
        ->and($formation->getRawOriginal('career_prospects'))->toContain('Journaliste')
        ->and($formation->translation('career_prospects', 'en'))->toContain('Journalist')
        ->and($formation->translation('description', 'mg'))->toContain('Mampahafantatra');

    Locales::apply('en');
    expect($formation->fresh()->career_prospects)->toContain('Press officer');
    Locales::reset();
});

it('never overwrites what an editor wrote', function () {
    $formation = Formation::factory()->create([
        'mention' => 'Droit',
        'description' => '<p>Notre texte à nous.</p>',
        'career_prospects' => '<p>TODO-CONTENU : débouchés professionnels.</p>',
    ]);

    runMigration('2026_11_14_100000_fill_formation_presentations.php');
    runMigration('2026_11_14_100000_fill_formation_presentations.php'); // rejouée : même résultat

    $formation->refresh();
    expect($formation->getRawOriginal('description'))->toBe('<p>Notre texte à nous.</p>')
        ->and($formation->translation('description', 'en'))->toBeNull()
        ->and($formation->getRawOriginal('career_prospects'))->toContain('Notariat');
});

it('leaves a mention it does not know untouched', function () {
    $formation = Formation::factory()->create(['mention' => 'Agroécologie', 'description' => '<p>TODO-CONTENU : x.</p>']);

    runMigration('2026_11_14_100000_fill_formation_presentations.php');

    expect($formation->refresh()->getRawOriginal('description'))->toContain('TODO-CONTENU');
});

it('adds the student testimonial once and sets the placeholder testimonials aside', function () {
    $placeholder = Testimonial::factory()->create(['author_name' => 'Témoignage vidéo 1 (TODO-CONTENU)', 'is_active' => true]);

    runMigration('2026_11_14_110000_add_student_testimonial.php');
    runMigration('2026_11_14_110000_add_student_testimonial.php');

    $kerena = Testimonial::where('author_name', 'Kerena Ranoarilalao')->get();
    expect($kerena)->toHaveCount(1)
        ->and($kerena->first()->is_active)->toBeTrue()
        ->and($kerena->first()->translation('quote', 'en'))->toContain('place of learning')
        ->and($placeholder->refresh()->is_active)->toBeFalse();
});

it('redirects the old Next.js addresses to their equivalent pages', function () {
    // la migration a déjà été jouée sur la base de test ; on vérifie la table de redirections
    expect(Redirect::map()['/formations/communication']['to'] ?? null)->toBe('/formations/licence-en-communication')
        ->and(Redirect::map()['/universite/equipe']['to'] ?? null)->toBe('/equipe');

    $this->get('/formations/droit')->assertRedirect('/formations/licence-en-droit')->assertStatus(301);
});
