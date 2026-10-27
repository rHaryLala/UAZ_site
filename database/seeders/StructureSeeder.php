<?php

namespace Database\Seeders;

use App\Enums\FacultyType;
use App\Enums\FormationLevel;
use App\Enums\PublicationStatus;
use App\Models\Faculty;
use App\Models\Formation;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Project;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

/**
 * Structure du site reprise de l'ancien site WordPress et de la maquette de l'équipe (docs/MAQUETTE.md).
 * Idempotent ; sert aussi en production. Tout ce qui n'est pas connu est marqué TODO-CONTENU
 * (voir docs/TODO-CONTENU.md) : aucune donnée factuelle n'est inventée.
 */
class StructureSeeder extends Seeder
{
    private const TODO = 'TODO-CONTENU';

    /** Domaines et mentions de licence (maquette, validées par l'équipe ; parcours à confirmer). */
    private const DOMAINS = [
        'Arts & lettres' => [
            'Théologie' => 'Pastorale · Biblique',
            'Communication' => 'Entreprise · Média et Journalisme',
            'Études anglophones' => 'Traduction et interprétation · Socio-linguistique',
        ],
        'Sciences sociales' => [
            'Gestion' => 'Management des affaires · Finance et comptabilité',
            'Droit' => 'Public · Privé',
        ],
        'Sciences de la santé' => [
            'Sciences infirmières' => 'Infirmerie générale · Maïeutique',
        ],
        'Sciences de l\'ingénierie' => [
            'Informatique' => 'Système d\'information et génie logiciel · Maintenance et administration de réseau',
        ],
    ];

    public function run(): void
    {
        $this->settings();
        $this->formations();
        $pages = $this->pages();
        $this->menus($pages);
    }

    private function settings(): void
    {
        $settings = SiteSetting::current();

        if ($settings->wasRecentlyCreated || blank($settings->tagline)) {
            $settings->update([
                'site_name' => 'Université Adventiste Zurcher',
                'tagline' => 'Préparer aujourd’hui les leaders de demain',
                'address' => 'PK 135, Route Nationale 7, Antsirabe, Madagascar',
                'email' => 'office@zurcher.edu.mg', // à valider (maquette)
                'phone' => null, // TODO-CONTENU : la maquette donne deux numéros différents
                'latitude' => null,
                'longitude' => null,
                'opening_hours' => [
                    ['days' => 'Lundi – jeudi', 'hours' => '7 h 30 – 12 h et 13 h 30 – 17 h 30'],
                    ['days' => 'Vendredi', 'hours' => '7 h 30 – 12 h'],
                ],
                'social_links' => [
                    'facebook' => 'https://www.facebook.com/uazsambaina',
                    'instagram' => 'https://www.instagram.com/adventist.university.zurcher/',
                    'linkedin' => 'https://www.linkedin.com/school/zurcher/',
                    'youtube' => 'https://www.youtube.com/@uaz-universiteadventistezu6053',
                    'tiktok' => 'https://www.tiktok.com/@uaz_zurcher',
                ],
                'footer_text' => 'Spiritus · Manus · Mens — Service.',
                'footer_legal' => '© Université Adventiste Zurcher',
                'home_hero_title' => 'Préparer aujourd’hui les leaders de demain',
                'home_hero_subtitle' => 'Excellence académique, vie étudiante dynamique et développement personnel : l’UAZ vous accompagne dans un cadre moderne, verdoyant et propice au bien-être.',
                // Chiffres de la maquette, à faire valider par l'équipe avant publication.
                'key_figures' => [
                    ['value' => 1995, 'suffix' => '', 'label' => 'Fondation de l’UAZ'],
                    ['value' => 7, 'suffix' => '', 'label' => 'Mentions de licence'],
                    ['value' => 180, 'suffix' => '', 'label' => 'Crédits ECTS en licence (3 ans)'],
                    ['value' => 200, 'suffix' => '+', 'label' => 'Hectares de campus'],
                ],
            ]);
        }
    }

    private function formations(): void
    {
        $order = 0;

        foreach (self::DOMAINS as $domain => $mentions) {
            $faculty = Faculty::firstOrCreate(
                ['name' => $domain],
                ['type' => FacultyType::Department, 'sort_order' => ++$order, 'is_active' => true],
            );

            foreach ($mentions as $mention => $parcours) {
                Formation::firstOrCreate(
                    ['title' => "Licence en $mention"],
                    [
                        'faculty_id' => $faculty->id,
                        'mention' => $mention,
                        'level' => FormationLevel::Licence,
                        'duration' => '3 ans (6 semestres)',
                        'credits' => 180,
                        'summary' => "Parcours : $parcours.",
                        'description' => '<p>'.self::TODO.' : présentation détaillée de la formation.</p>',
                        'career_prospects' => '<p>'.self::TODO.' : débouchés professionnels.</p>',
                        'admission_requirements' => '<p>Admission après le baccalauréat, selon la mention et les critères de la filière. '.self::TODO.' : conditions détaillées.</p>',
                        'is_featured' => true,
                        'sort_order' => $order,
                        'status' => PublicationStatus::Published,
                        'published_at' => now(),
                    ],
                );
            }
        }
    }

    /** @return array<string, Page> */
    private function pages(): array
    {
        $make = function (string $key, string $title, ?string $slug = null, ?Page $parent = null, ?string $summary = null, int $sort = 0, PublicationStatus $status = PublicationStatus::Published): Page {
            return Page::firstOrCreate(['key' => $key], [
                'title' => $title,
                'slug' => $slug,
                'parent_id' => $parent?->id,
                'summary' => $summary,
                'sort_order' => $sort,
                'blocks' => [[
                    'type' => 'rich_text',
                    'data' => ['heading' => null, 'content' => '<p>'.self::TODO.' : contenu de la page « '.$title.' ».</p>', 'width' => 'normal'],
                ]],
                'status' => $status,
                'published_at' => now(),
            ]);
        };

        $pages = [];
        $pages['home'] = $make('home', 'Accueil', 'accueil');
        $pages['about'] = $make('about', 'À propos', 'a-propos', null, 'Histoire, vision et valeurs de l\'Université Adventiste Zurcher.', 1);
        $pages['admissions'] = $make('admissions', 'Admissions', 'admissions', null, 'Admission après le baccalauréat, selon la mention et les critères de chaque filière.', 2, PublicationStatus::Draft);
        $pages['licence'] = $make('admissions-licence', 'Licence', 'licence', $pages['admissions'], null, 1);
        $pages['master'] = $make('admissions-master', 'Master', 'master', $pages['admissions'], null, 2);
        $pages['fees'] = $make('admissions-fees', 'Frais académiques', 'frais-academiques', $pages['admissions'], null, 3);
        $pages['downloads'] = $make('admissions-downloads', 'Téléchargements', 'telechargements', $pages['admissions'], null, 4);
        $pages['student-life'] = $make('student-life', 'Vie étudiante', 'vie-etudiante', null, 'Campus, bibliothèque, cafétéria, dortoirs et accompagnement.', 3, PublicationStatus::Draft);
        $pages['library'] = $make('student-life-library', 'Bibliothèque', 'bibliotheque', $pages['student-life'], null, 1);
        $pages['cafeteria'] = $make('student-life-cafeteria', 'Cafétéria', 'cafeteria', $pages['student-life'], null, 2);
        $pages['dorms'] = $make('student-life-dorms', 'Dortoirs', 'dortoirs', $pages['student-life'], null, 3);
        $pages['lmd'] = $make('lmd', 'Système LMD', 'systeme-lmd', null, 'Licence, Master, Doctorat : le système d\'études de l’UAZ.', 4);
        // Seuls faits connus (page « Personnel » du site actuel, repris dans l'équipe) ; mission, axes et publications à fournir.
        $pages['research'] = $make('research', 'Centre de recherche (ZRC)', 'centre-de-recherche', null, 'Le centre de recherche de l\'Université Adventiste Zurcher.', 6);
        $pages['timetables'] = $make('timetables', 'Emplois du temps', 'emplois-du-temps', null, null, 5);
        // Page de dons et de projets (vue dédiée) ; l'image d'en-tête se choisit dans le back-office (champ « Image »).
        $pages['support'] = $make('support', 'Soutenir l\'UAZ', 'soutenir', null, 'Accompagnez l\'Université Adventiste Zurcher et ses étudiants : découvrez les projets de l\'UAZ et soutenez-les.', 7);

        $this->projects();

        return $pages;
    }

    /**
     * Projets de l'« Appel aux dons - UAZ » (document fourni par l'UAZ). TODO-CONTENU : photos de l'incendie et plan du
     * dortoir à ajouter (image du projet / présentation détaillée dans le back-office).
     */
    private function projects(): void
    {
        Project::firstOrCreate(['slug' => 'un-grand-dortoir-pour-les-garcons'], [
            'title' => 'Un grand dortoir pour les garçons',
            'summary' => 'Le 9 octobre 2023, les logements des garçons ont brûlé en quelques instants. Les étudiants sont logés provisoirement dans le bâtiment de l\'orphelinat, en attendant une solution permanente.',
            'body' => '<p>Le 9 octobre 2023 au petit matin, les logements des garçons ont brûlé en quelques instants. Les étudiants sont pour l\'instant logés dans le bâtiment de l\'orphelinat en attendant une solution permanente.</p>'
                .'<p>Le projet : construire un grand dortoir pour les garçons.</p>'
                .'<h2>Montant : 275 000 €</h2>',
            'budget' => '275 000 €',
            'status_label' => 'Appel aux dons',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Project::firstOrCreate(['slug' => 'des-logements-pour-le-personnel'], [
            'title' => 'Des logements pour le personnel',
            'summary' => 'Pour accompagner le développement de l\'UAZ, 5 logements et 1 bâtiment d\'accueil de groupes sont nécessaires.',
            'body' => '<p>Pour accompagner le développement de l\'UAZ, 5 logements et 1 bâtiment d\'accueil de groupes sont nécessaires.</p>'
                .'<h2>Montant : 160 000 €</h2>',
            'budget' => '160 000 €',
            'status_label' => 'Appel aux dons',
            'sort_order' => 2,
            'is_active' => true,
        ]);
    }

    /** @param  array<string, Page>  $pages */
    private function menus(array $pages): void
    {
        $page = fn (string $label, string $key) => ['label' => $label, 'type' => 'page', 'page_id' => $pages[$key]->id, 'children' => []];
        $url = fn (string $label, string $path) => ['label' => $label, 'type' => 'url', 'url' => $path, 'children' => []];

        Menu::firstOrCreate(['handle' => 'header'], [
            'name' => 'Menu principal',
            'items' => [
                $url('Formations', '/formations'),
                array_merge($url('Admissions', '#'), ['children' => [
                    $page('Licence', 'licence'), $page('Master', 'master'),
                    $page('Frais académiques', 'fees'), $page('Téléchargements', 'downloads'),
                ]]),
                array_merge($url('Vie étudiante', '#'), ['children' => [
                    $page('Bibliothèque', 'library'), $page('Cafétéria', 'cafeteria'), $page('Dortoirs', 'dorms'),
                ]]),
                $page('Centre de recherche', 'research'),
                $page('À propos', 'about'),
            ],
        ]);

        // Barre du haut de la maquette. TODO-CONTENU : « Étudiants », « Alumni », « Donner » et « Se connecter » n'ont
        // pas encore de page dédiée ; les destinations ci-dessous sont provisoires (docs/TODO-CONTENU.md).
        Menu::firstOrCreate(['handle' => 'topbar'], [
            'name' => 'Barre du haut',
            'items' => [
                $page('Étudiants', 'library'),
                $url('Notre équipe', '/equipe'),
                array_merge($url('Alumni', 'https://alumni-auz.api.mg/'), ['new_tab' => true]),
                $page('Donner', 'support'),
                $url('Yearbook', '/yearbook'),
                $url('Se connecter', '/admin'),
            ],
        ]);

        Menu::firstOrCreate(['handle' => 'footer'], [
            'name' => 'Pied de page',
            'items' => [
                $url('Actualités', '/actualites'),
                $url('Notre équipe', '/equipe'),
                $url('Événements', '/evenements'),
                $url('Galerie', '/galerie'),
                $page('Emplois du temps', 'timetables'),
                $page('Téléchargements', 'downloads'),
                $url('Contact', '/contact'),
            ],
        ]);
    }
}
