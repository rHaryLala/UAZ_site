<?php

use App\Models\Testimonial;
use Illuminate\Database\Migrations\Migration;

/**
 * Ajoute le témoignage publié sur l'accueil du site zurcher.edu.mg (texte français et anglais ; le site actuel n'a pas de version
 * malagasy du texte). Les trois témoignages vidéo encore en TODO-CONTENU (auteur inconnu) sont mis de côté : ils s'affichaient tels quels
 * sur l'accueil. Ils ne sont pas supprimés : il suffit de les réactiver une fois l'auteur renseigné. La photo est rattachée par
 * `php artisan media:import-site-images`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Testimonial::query()->firstOrCreate(
            ['author_name' => 'Kerena Ranoarilalao'],
            [
                'author_role' => 'Étudiante en Communication, promotion SHIELD 2025',
                'quote' => 'L\'UAZ n\'est pas seulement synonyme d\'exigence : c\'est un lieu d\'apprentissage, d\'épanouissement et d\'opportunités qui enrichit véritablement le parcours étudiant.',
                'sort_order' => 0,
                'is_active' => true,
                'translations' => ['en' => [
                    'author_role' => 'Communication student, SHIELD class of 2025',
                    'quote' => 'UAZ is not only about high standards: it is a place of learning, growth and opportunity that truly enriches the student journey.',
                ]],
            ],
        );

        Testimonial::query()->where('author_name', 'like', '%TODO-CONTENU%')->update(['is_active' => false]);
    }

    public function down(): void
    {
        // Les contenus créés sont conservés.
    }
};
