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
                'phone' => '+261 34 44 931 07', // numéro de l'UAZ confirmé par l'équipe (27 octobre 2026)
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

    /** Texte du Manuel de l'étudiant, section 3.3, mot pour mot (la page « Association des étudiants » en tire sa mise en page ; ce texte sert à la recherche et à l'édition). */
    public const ASSOCIATION_TEXT = '<h2>3.3 L’Association des étudiants</h2>'
        .'<p>L’Association des étudiants (SA) est l’entité qui coordonne toutes les activités (cf sous-titre 3.4) prévues de l’année sous la supervision du Vice-Recteur chargé des affaires estudiantines (VRAE). Les officiants de la SA sont des étudiants élus par tous les étudiants inscrits pendant l’année académique.</p>'
        .'<p>Quand un(e) étudiant(e) désire se présenter au poste du Président de SA, il/elle doit former une équipe composée d’au plus 15 noms. Après l’appel à candidature lancé officiellement, les candidatures retenues feront l’objet d’un entretien avec le VRAE.</p>'
        .'<p>L’Association des étudiants est tenue s’engager effectivement à promouvoir tous les projets et programmes émanant directement du VRAE d’après le fonctionnement établi par ce dernier. En discussion d’affaire, les deux parties trouveront les meilleurs moyens de les réaliser. Quoi que soit, l’Association se doit de faire desdits projets et programmes une priorité, et ainsi les figurera en emphase dans sa planification.</p>';

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
        // Texte du Manuel de l'étudiant (section 3.3), repris tel quel ; la mise en page est dans resources/views/pages/association.blade.php.
        $pages['association'] = $make('student-life-association', 'Association des étudiants', 'association-des-etudiants', $pages['student-life'], 'L\'Association des étudiants (SA) coordonne toutes les activités prévues de l\'année, sous la supervision du Vice-Recteur chargé des affaires estudiantines.', 4);
        if (str_contains(json_encode($pages['association']->blocks), self::TODO)) {
            $pages['association']->update(['blocks' => [['type' => 'rich_text', 'data' => ['heading' => null, 'content' => self::ASSOCIATION_TEXT, 'width' => 'normal']]]]);
        }
        $pages['lmd'] = $make('lmd', 'Système LMD', 'systeme-lmd', null, 'Licence, Master, Doctorat : le système d\'études de l’UAZ.', 4);
        // Seuls faits connus (page « Personnel » du site actuel, repris dans l'équipe) ; mission, axes et publications à fournir.
        $pages['research'] = $make('research', 'Centre de recherche (ZRC)', 'centre-de-recherche', null, 'Le centre de recherche de l\'Université Adventiste Zurcher.', 6);
        $pages['timetables'] = $make('timetables', 'Emplois du temps', 'emplois-du-temps', null, null, 5);
        // Page de dons et de projets (vue dédiée) ; l'image d'en-tête se choisit dans le back-office (champ « Image »).
        $pages['support'] = $make('support', 'Soutenir l\'UAZ', 'soutenir', null, 'Accompagnez l\'Université Adventiste Zurcher et ses étudiants : découvrez les projets de l\'UAZ et soutenez-les.', 7);

        $pages['privacy'] = $this->privacyPage();

        $this->projects();

        return $pages;
    }

    /**
     * Confidentialité et cookies. Rédigée d'après ce que le site fait réellement (deux cookies techniques, formulaire de contact,
     * aucun outil de mesure) ; reste en brouillon tant que l'UAZ n'a pas validé les points TODO-CONTENU (docs/TODO-CONTENU.md).
     * Le lien du pied de page n'apparaît qu'une fois la page publiée.
     */
    private function privacyPage(): Page
    {
        $block = fn (string $heading, string $html) => ['type' => 'rich_text', 'data' => ['heading' => $heading, 'content' => $html, 'width' => 'normal']];

        return Page::firstOrCreate(['key' => 'privacy'], [
            'title' => 'Confidentialité et cookies',
            'slug' => 'confidentialite',
            'summary' => 'Les données que ce site utilise, les cookies qu\'il dépose et vos droits.',
            'sort_order' => 8,
            'status' => PublicationStatus::Draft,
            'published_at' => now(),
            'blocks' => [
                $block('En bref', '<p>Ce site n\'utilise <strong>aucun cookie publicitaire, aucun outil de mesure d\'audience et aucun traceur de réseau social</strong>. '
                    .'Il ne dépose que deux cookies techniques, indispensables à son fonctionnement, qui ne nécessitent donc pas de bandeau de consentement. '
                    .'Les seules données personnelles que nous recueillons sont celles que vous nous envoyez vous-même par le formulaire de contact.</p>'),
                $block('Qui est responsable de ces données ?', '<p>Université Adventiste Zurcher, PK 135, Route Nationale 7, Antsirabe, Madagascar. '
                    .'Pour toute question sur vos données : <a href="mailto:office@zurcher.edu.mg">office@zurcher.edu.mg</a>. '
                    .self::TODO.' : confirmer l\'adresse et le service chargé des données personnelles.</p>'),
                $block('Les cookies de ce site', '<p>Deux cookies sont déposés lors de votre visite :</p>'
                    .'<ul><li><strong>universite-adventiste-zurcher-session</strong> : relie vos actions pendant la visite (par exemple l\'envoi du formulaire de contact). Il disparaît au bout de 2 heures.</li>'
                    .'<li><strong>XSRF-TOKEN</strong> : protège les formulaires contre les envois frauduleux. Même durée de vie.</li></ul>'
                    .'<p>Ils ne servent ni à vous suivre d\'une visite à l\'autre, ni à des statistiques, ni à de la publicité. Ils ne contiennent aucune information vous identifiant. '
                    .'Vous pouvez les refuser dans les réglages de votre navigateur : le site reste lisible, mais le formulaire de contact risque de ne plus fonctionner.</p>'),
                $block('Mémoire de votre navigateur', '<p>Sans cookie, le site retient dans votre navigateur (stockage local) <strong>une seule information</strong> : si la fenêtre d\'abonnement à la lettre d\'information vous a déjà été proposée, et si vous vous êtes abonné. '
                    .'Elle sert uniquement à ne pas vous la reproposer à chaque page : vous la revoyez au plus une fois tous les 20 jours, tant que vous n\'êtes pas abonné. '
                    .'Cette information reste sur votre appareil et n\'est jamais envoyée au site ; vous pouvez l\'effacer en vidant les données du site dans votre navigateur.</p>'),
                $block('La lettre d\'information', '<p>Si vous vous abonnez, nous enregistrons <strong>votre adresse e-mail</strong>, la date de votre demande et celle de votre confirmation. '
                    .'Nous vous envoyons d\'abord un e-mail de confirmation : sans clic sur le lien, vous ne recevez rien et personne ne peut vous inscrire à votre place. '
                    .'Chaque message contient un lien pour vous désinscrire en un clic. Votre adresse n\'est utilisée que pour cette lettre.</p>'
                    .'<p>Durée de conservation après désinscription : '.self::TODO.' (à fixer par l\'UAZ).</p>'),
                $block('Le formulaire de contact', '<p>Quand vous nous écrivez, nous recevons les informations que vous saisissez : <strong>nom, adresse e-mail, téléphone (facultatif), sujet et message</strong>. '
                    .'Elles servent uniquement à vous répondre. Votre message est transmis par e-mail à l\'UAZ et conservé dans l\'espace d\'administration du site, accessible aux seules personnes autorisées.</p>'
                    .'<p>Pour limiter les messages automatiques (spam), nous enregistrons aussi une <strong>empreinte chiffrée et non réversible de votre adresse IP</strong> '
                    .'(l\'adresse elle-même n\'est pas conservée) et le nom de votre navigateur.</p>'
                    .'<p>Durée de conservation : '.self::TODO.' (à fixer par l\'UAZ).</p>'),
                $block('Services externes', '<ul><li><strong>Météo du pied de page</strong> : récupérée par notre serveur auprès d\'Open-Meteo. Votre navigateur ne contacte pas ce service.</li>'
                    .'<li><strong>Carte de la page Contact</strong> : fournie par OpenStreetMap. Son affichage lui transmet votre adresse IP, comme tout contenu intégré ; nous n\'y ajoutons aucun cookie.</li>'
                    .'<li><strong>Liens vers d\'autres sites</strong> (Facebook, Instagram, LinkedIn, YouTube, TikTok, Registrar, Moodle, Alumni) : ce sont de simples liens. '
                    .'Une fois sur ces sites, leurs propres règles s\'appliquent.</li></ul>'),
                $block('Vos droits', '<p>Vous pouvez nous demander l\'accès aux informations que vous nous avez envoyées, leur correction ou leur suppression, en écrivant à '
                    .'<a href="mailto:office@zurcher.edu.mg">office@zurcher.edu.mg</a>. '.self::TODO.' : compléter avec le cadre légal retenu par l\'UAZ (loi malgache sur la protection des données personnelles) après validation.</p>'),
                $block('Mise à jour', '<p>Dernière mise à jour : 28 octobre 2026. Cette page est modifiée si le site change sa façon d\'utiliser des cookies ou des données (par exemple en ajoutant un outil de statistiques).</p>'),
            ],
        ]);
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
                    $page('Frais académiques', 'fees'),
                ]]),
                array_merge($url('Vie étudiante', '#'), ['children' => [
                    $page('Bibliothèque', 'library'), $page('Cafétéria', 'cafeteria'), $page('Dortoirs', 'dorms'),
                    $page('Association des étudiants', 'association'),
                ]]),
                array_merge($url('Ressources', '#'), ['children' => [
                    $page('Téléchargements', 'downloads'), $url('Galerie', '/galerie'),
                    $page('Emplois du temps', 'timetables'), $url('Événements', '/evenements'),
                    $page('Système LMD', 'lmd'),
                ]]),
                $page('Centre de recherche', 'research'),
                $page('À propos', 'about'),
            ],
        ]);

        // Barre du haut de la maquette. La dernière entrée est la pastille dorée : « Se connecter » ouvre une fenêtre de choix
        // (Registrar / Moodle). TODO-CONTENU : « Alumni » et « Donner » restent à confirmer (docs/TODO-CONTENU.md).
        Menu::firstOrCreate(['handle' => 'topbar'], [
            'name' => 'Barre du haut',
            'items' => [
                $url('Notre équipe', '/equipe'),
                array_merge($url('Alumni', 'https://alumni-auz.api.mg/'), ['new_tab' => true]),
                $url('Yearbook', '/yearbook'),
                // « S'abonner » ouvre la fenêtre de la lettre d'information (pas une page).
                $url('S\'abonner', '#newsletter'),
                $url('Actualités', '/actualites'),
                // Catalogue de la bibliothèque : accessible dès le haut de chaque page (site de la bibliothèque, nouvel onglet).
                array_merge($url('Catalogue de la bibliothèque', 'https://library.zurcher.edu.mg'), ['new_tab' => true]),
                $page('Donner', 'support'),
                array_merge($url('Se connecter', '#'), ['children' => [
                    array_merge($url('Registrar — bureau académique', 'https://registrar.zurcher.edu.mg'), ['new_tab' => true]),
                    array_merge($url('Moodle — cours en ligne', 'https://moodle.zurcher.edu.mg'), ['new_tab' => true]),
                ]]),
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
