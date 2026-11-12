<?php

namespace Database\Seeders;

use App\Models\Faculty;
use App\Models\Formation;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\Project;
use App\Models\SiteSetting;
use App\Support\FormationContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Traductions (anglais, malagasy) du contenu de structure créé par StructureSeeder : réglages du site, facultés, formations,
 * titres des pages, projets à soutenir. Idempotent : ne remplit que les champs encore vides, jamais une traduction saisie dans
 * le back-office. Le contenu éditorial importé (actualités, équipe, descriptions d'images…) se traduit dans le back-office.
 *
 * TODO-CONTENU : les textes malagasy sont à faire relire par un locuteur natif avant publication (docs/TODO-CONTENU.md).
 */
class ContentTranslationsSeeder extends Seeder
{
    /** Parcours de licence : français => [anglais, malagasy]. */
    private const TRACKS = [
        'Pastorale' => ['Pastoral studies', 'Fitondrana ara-pitandremana'],
        'Biblique' => ['Biblical studies', 'Baiboly'],
        'Entreprise' => ['Business', 'Orinasa'],
        'Média et Journalisme' => ['Media and Journalism', 'Haino aman-jery sy Gazety'],
        'Traduction et interprétation' => ['Translation and interpreting', 'Fandikan-teny sy fandikan-tenin\'ny vava'],
        'Socio-linguistique' => ['Sociolinguistics', 'Sosio-lingwistika'],
        'Management des affaires' => ['Business management', 'Fitantanana orinasa'],
        'Finance et comptabilité' => ['Finance and accounting', 'Vola sy kaonty'],
        'Public' => ['Public', 'Ho an\'ny daholobe'],
        'Privé' => ['Private', 'Tsy miankina amin\'ny fanjakana'],
        'Infirmerie générale' => ['General nursing', 'Fitsaboana ankapobeny'],
        'Maïeutique' => ['Midwifery', 'Fitaizana ny reny (mpiheritra)'],
        'Système d\'information et génie logiciel' => ['Information systems and software engineering', 'Rafitra fampahalalana sy injeniera lojisialy'],
        'Maintenance et administration de réseau' => ['Network maintenance and administration', 'Fikarakarana sy fitantanana tambajotra'],
    ];

    /** Mentions : français => [anglais, malagasy]. */
    private const MENTIONS = [
        'Théologie' => ['Theology', 'Teolojia'],
        'Communication' => ['Communication', 'Fifandraisana'],
        'Études anglophones' => ['English studies', 'Fianarana anglisy'],
        'Gestion' => ['Management', 'Fitantanana'],
        'Droit' => ['Law', 'Lalàna'],
        'Sciences infirmières' => ['Nursing', 'Siansa mpitsabo'],
        'Informatique' => ['Computer science', 'Informatika'],
    ];

    /** Facultés / départements : français => [anglais, malagasy]. */
    private const FACULTIES = [
        'Arts & lettres' => ['Arts & humanities', 'Zavakanto sy literatiora'],
        'Sciences sociales' => ['Social sciences', 'Siansa sosialy'],
        'Sciences de la santé' => ['Health sciences', 'Siansa momba ny fahasalamana'],
        'Sciences de l\'ingénierie' => ['Engineering sciences', 'Siansa injeniera'],
    ];

    /** Pages : clé => [titre en, titre mg, résumé en, résumé mg]. */
    private const PAGES = [
        'home' => ['Home', 'Fandraisana'],
        'about' => ['About', 'Momba ny UAZ', 'History, vision and values of Zurcher Adventist University.', 'Tantara, fahitana ary soatoavin\'ny Oniversite Advantista Zurcher.'],
        'admissions' => ['Admissions', 'Fidirana', 'Admission after the baccalaureate, depending on the major and the requirements of each program.', 'Fidirana aorian\'ny bakalorea, araka ny sehatra sy ny fepetra isaky ny taranja.'],
        'admissions-licence' => ['Bachelor\'s (Licence)', 'Licence'],
        'admissions-master' => ['Master\'s', 'Master'],
        'admissions-fees' => ['Tuition and fees', 'Saram-pianarana'],
        'admissions-downloads' => ['Downloads', 'Fakana'],
        'student-life' => ['Student life', 'Fiainan\'ny mpianatra', 'Campus, library, cafeteria, dormitories and support.', 'Campus, tranomboky, kafeteria, trano fatoriana ary fanohanana.'],
        'student-life-library' => ['Library', 'Tranomboky'],
        'student-life-cafeteria' => ['Cafeteria', 'Kafeteria'],
        'student-life-dorms' => ['Dormitories', 'Trano fatoriana'],
        'student-life-association' => ['Student association', 'Fikambanan\'ny mpianatra', 'The Student Association (SA) coordinates all the activities planned for the year, under the supervision of the Vice-Rector for Student Affairs.', 'Ny Fikambanan\'ny mpianatra (SA) no mandrindra ny hetsika rehetra voalahatra amin\'ny taona, eo ambany fanaraha-mason\'ny Lefitra Filohan\'ny Oniversite miandraikitra ny raharaha momba ny mpianatra.'],
        'lmd' => ['LMD system', 'Rafitra LMD', 'Bachelor\'s, Master\'s, Doctorate: the UAZ study system.', 'Licence, Master, Doctorat: ny rafitry ny fianarana ao amin\'ny UAZ.'],
        'research' => ['Research Center (ZRC)', 'Foibe fikarohana (ZRC)', 'The research center of Zurcher Adventist University.', 'Ny foibe fikarohan\'ny Oniversite Advantista Zurcher.'],
        'timetables' => ['Class schedules', 'Fandaharam-potoana'],
        'support' => ['Support UAZ', 'Manohana ny UAZ', 'Support Zurcher Adventist University and its students: discover UAZ\'s projects and support them.', 'Ampio ny Oniversite Advantista Zurcher sy ny mpianany: fantaro ny tetikasan\'ny UAZ ary hohano izy ireo.'],
        'privacy' => ['Privacy and cookies', 'Tsiambaratelo sy cookies', 'The data this site uses, the cookies it sets and your rights.', 'Ny angona ampiasain\'ity tranonkala ity, ny cookies apetrany ary ny zonao.'],
    ];

    public function run(): void
    {
        $this->settings();
        $this->faculties();
        $this->formations();
        $this->pages();
        $this->projects();
        $this->mediaAlts();
    }

    /** Ajoute les textes manquants d'une langue (jamais d'écrasement). */
    private function fill(Model $model, string $locale, array $values): void
    {
        $translations = $model->translations ?? [];
        $changed = false;

        foreach ($values as $attribute => $value) {
            if (blank($translations[$locale][$attribute] ?? null)) {
                $translations[$locale][$attribute] = $value;
                $changed = true;
            }
        }

        if ($changed) {
            $model->translations = $translations;
            $model->save();
        }
    }

    private function settings(): void
    {
        $settings = SiteSetting::current();

        $figures = [
            'Fondation de l’UAZ' => ['UAZ founded', 'Naorina ny UAZ'],
            'Mentions de licence' => ['Bachelor\'s majors', 'Sehatra Licence'],
            'Crédits ECTS en licence (3 ans)' => ['ECTS credits in a Bachelor\'s (3 years)', 'Crédits ECTS amin\'ny Licence (3 taona)'],
            'Hectares de campus' => ['Hectares of campus', 'Hektara campus'],
        ];
        $days = ['Lundi – jeudi' => ['Monday – Thursday', 'Alatsinainy – Alakamisy'], 'Vendredi' => ['Friday', 'Zoma']];
        $hours = [
            '7 h 30 – 12 h et 13 h 30 – 17 h 30' => ['7:30 am – 12 pm and 1:30 pm – 5:30 pm', '7 ora 30 – 12 ora sy 13 ora 30 – 17 ora 30'],
            '7 h 30 – 12 h' => ['7:30 am – 12 pm', '7 ora 30 – 12 ora'],
        ];

        foreach ([0 => 'en', 1 => 'mg'] as $i => $locale) {
            $this->fill($settings, $locale, array_filter([
                'site_name' => ['Zurcher Adventist University', 'Oniversite Advantista Zurcher'][$i],
                'tagline' => ['Preparing tomorrow\'s leaders today', 'Manomana ankehitriny ny mpitarika ho avy'][$i],
                'home_hero_title' => ['Preparing tomorrow\'s leaders today', 'Manomana ankehitriny ny mpitarika ho avy'][$i],
                'home_hero_subtitle' => [
                    'Academic excellence, a vibrant student life and personal growth: UAZ is with you every step of the way, in a modern, green setting that supports well-being.',
                    'Fahaiza-manao ambony ara-pianarana, fiainana mavitrika eo amin\'ny mpianatra ary fampandrosoana manokana: ny UAZ dia miaraka aminao ao anatin\'ny tontolo maoderina sy maitso mandray anjara amin\'ny fahasalamana.',
                ][$i],
                'footer_text' => ['Spiritus · Manus · Mens — Service.', 'Spiritus · Manus · Mens — Fanompoana.'][$i],
                'footer_legal' => ['© Zurcher Adventist University', '© Oniversite Advantista Zurcher'][$i],
                // Libellés dans l'ordre des chiffres actuels ; un chiffre ajouté depuis reste en français.
                'key_figures' => collect($settings->key_figures ?? [])->map(fn ($f) => ['label' => ($figures[$f['label']] ?? [null, null])[$i]])->all(),
                'opening_hours' => collect($settings->opening_hours ?? [])->map(fn ($h) => [
                    'days' => ($days[$h['days']] ?? [null, null])[$i],
                    'hours' => ($hours[$h['hours']] ?? [null, null])[$i],
                ])->all(),
            ]));
        }
    }

    private function faculties(): void
    {
        foreach (self::FACULTIES as $french => [$en, $mg]) {
            if ($faculty = Faculty::where('name', $french)->first()) {
                $this->fill($faculty, 'en', ['name' => $en]);
                $this->fill($faculty, 'mg', ['name' => $mg]);
            }
        }
    }

    private function formations(): void
    {
        foreach (self::MENTIONS as $mention => [$en, $mg]) {
            $formation = Formation::where('title', "Licence en $mention")->first();
            if (! $formation) {
                continue;
            }

            // « Parcours : A · B. » : on traduit chaque parcours cité dans le résumé d'origine.
            $tracks = collect(explode(' · ', rtrim(str_replace('Parcours : ', '', (string) $formation->summary), '.')))->map(fn ($t) => self::TRACKS[trim($t)] ?? null);
            $summary = fn (int $i, string $lead) => $tracks->every(fn ($t) => $t !== null) && $tracks->isNotEmpty()
                ? $lead.' '.$tracks->map(fn ($t) => $t[$i])->implode(' · ').'.'
                : null;

            $this->fill($formation, 'en', array_filter([
                'title' => "Bachelor's in $en", 'mention' => $en, 'duration' => '3 years (6 semesters)', 'summary' => $summary(0, 'Tracks:'),
                'description' => FormationContent::description($mention, 'en'), 'career_prospects' => FormationContent::careers($mention, 'en'),
            ]));
            $this->fill($formation, 'mg', array_filter([
                'title' => "Licence amin'ny $mg", 'mention' => $mg, 'duration' => 'Taona 3 (semestra 6)', 'summary' => $summary(1, 'Lalana:'),
                'description' => FormationContent::description($mention, 'mg'), 'career_prospects' => FormationContent::careers($mention, 'mg'),
            ]));
        }
    }

    private function pages(): void
    {
        foreach (self::PAGES as $key => $texts) {
            $page = Page::where('key', $key)->first();
            if (! $page) {
                continue;
            }

            $this->fill($page, 'en', array_filter(['title' => $texts[0], 'summary' => $texts[2] ?? null]));
            $this->fill($page, 'mg', array_filter(['title' => $texts[1], 'summary' => $texts[3] ?? null]));
        }

        if ($privacy = Page::where('key', 'privacy')->first()) {
            $this->fill($privacy, 'en', ['blocks' => $this->privacyBlocksInEnglish()]);
        }
    }

    private function projects(): void
    {
        $texts = [
            'un-grand-dortoir-pour-les-garcons' => [
                'en' => [
                    'title' => 'A large dormitory for men',
                    'summary' => 'On 9 October 2023, the men\'s housing burned down in a matter of moments. The students are temporarily housed in the orphanage building, pending a permanent solution.',
                    'body' => '<p>On the early morning of 9 October 2023, the men\'s housing burned down in a matter of moments. For now, the students are housed in the orphanage building, pending a permanent solution.</p><p>The project: to build a large dormitory for men.</p><h2>Amount: €275,000</h2>',
                    'budget' => '€275,000', 'status_label' => 'Call for donations',
                ],
                'mg' => [
                    'title' => 'Trano fatoriana lehibe ho an\'ny lehilahy',
                    'summary' => 'Tamin\'ny 9 Oktobra 2023, may tao anatin\'ny fotoana fohy ny trano fonenan\'ny lehilahy. Mipetraka vonjimaika ao amin\'ny tranon\'ny kamboty ny mpianatra mandra-pahitana vahaolana maharitra.',
                    'body' => '<p>Tamin\'ny maraina be tamin\'ny 9 Oktobra 2023, may tao anatin\'ny fotoana fohy ny trano fonenan\'ny lehilahy. Ankehitriny, mipetraka ao amin\'ny tranon\'ny kamboty ny mpianatra mandra-pahitana vahaolana maharitra.</p><p>Ny tetikasa: ny hanorina trano fatoriana lehibe ho an\'ny lehilahy.</p><h2>Vola: 275 000 €</h2>',
                    'budget' => '275 000 €', 'status_label' => 'Antso fanomezana',
                ],
            ],
            'des-logements-pour-le-personnel' => [
                'en' => [
                    'title' => 'Housing for staff',
                    'summary' => 'To support UAZ\'s development, 5 houses and 1 group-reception building are needed.',
                    'body' => '<p>To support UAZ\'s development, 5 houses and 1 group-reception building are needed.</p><h2>Amount: €160,000</h2>',
                    'budget' => '€160,000', 'status_label' => 'Call for donations',
                ],
                'mg' => [
                    'title' => 'Trano fonenana ho an\'ny mpiasa',
                    'summary' => 'Mba hanohanana ny fampandrosoan\'ny UAZ, ilaina ny trano fonenana 5 sy tranobe 1 handraisana vondrona.',
                    'body' => '<p>Mba hanohanana ny fampandrosoan\'ny UAZ, ilaina ny trano fonenana 5 sy tranobe 1 handraisana vondrona.</p><h2>Vola: 160 000 €</h2>',
                    'budget' => '160 000 €', 'status_label' => 'Antso fanomezana',
                ],
            ],
        ];

        foreach ($texts as $slug => $byLocale) {
            if ($project = Project::where('slug', $slug)->first()) {
                foreach ($byLocale as $locale => $values) {
                    $this->fill($project, $locale, $values);
                }
            }
        }
    }

    /** Textes alternatifs des photos des pages Campus, Dortoirs et Admissions, retrouvées par leur texte français exact. */
    private function mediaAlts(): void
    {
        $alts = [
            'Vue aérienne du campus de l\'UAZ au coucher du soleil : bâtiments, pins et collines à l\'horizon' => ['Aerial view of the UAZ campus at sunset: buildings, pine trees and hills on the horizon', 'Sary avy ambony ny campus UAZ amin\'ny filentehan\'ny masoandro: trano, kapiky ary havoana any lavitra'],
            'Trois diplômés de l’UAZ en toge noire et étole jaune, dans le campus, lors de la remise des diplômes' => ['Three UAZ graduates in black gowns and yellow stoles, on campus, at the graduation ceremony', 'Mpianatra telo nahazo diplaoma ao amin\'ny UAZ miaraka amin\'ny akanjo mainty sy lamba mavo, ao anatin\'ny campus, nandritra ny lanonana fanomezana diplaoma'],
            'Salle de la bibliothèque de l’UAZ : rayonnages, grandes tables ovales et plafond en bois' => ['UAZ library hall: shelves, large oval tables and a wooden ceiling', 'Efitrano ao amin\'ny tranomboky UAZ: talantalana, latabatra lehibe ovaly ary valindrihana hazo'],
            'Quatre étudiants tenant leur mémoire relié, posant avec un enseignant devant un rideau sombre' => ['Four students holding their bound dissertations, posing with a teacher in front of a dark curtain', 'Mpianatra efatra mitondra ny mémoire nofonosina, mandray sary miaraka amin\'ny mpampianatra eo anoloan\'ny lamba fixana maizina'],
            'Une chambre du dortoir : lits superposés en bois, table fleurie et rideaux à tournesols' => ['A dormitory room: wooden bunk beds, a table with flowers and sunflower curtains', 'Efitra iray ao amin\'ny trano fatoriana: farafara roa rihana vita amin\'ny hazo, latabatra misy voninkazo ary lamba fixana'],
            'Cinq étudiants travaillent sur leurs ordinateurs autour d\'une table, dans leur chambre' => ['Five students working on their computers around a table in their room', 'Mpianatra dimy miasa amin\'ny solosainany manodidina latabatra ao amin\'ny efitra misy azy'],
            'Deux étudiants sur leurs ordinateurs portables, à la table de leur chambre' => ['Two students on their laptops at the table in their room', 'Mpianatra roa amin\'ny solosaina finday eo ambonin\'ny latabatra ao amin\'ny efitra misy azy'],
            'Des étudiantes étudient sur leurs lits superposés, ordinateurs et documents à portée de main' => ['Female students studying on their bunk beds, with computers and documents within reach', 'Mpianatra vavy mianatra eo amin\'ny farafara roa rihana misy azy, solosaina sy antontan-taratasy eo akaikiny'],
            'Quatre étudiantes posent dans leur chambre, sous une moustiquaire, près d\'une table fleurie' => ['Four female students posing in their room, under a mosquito net, beside a table with flowers', 'Mpianatra vavy efatra mandray sary ao amin\'ny efitra misy azy, ambanin\'ny lay, akaikin\'ny latabatra misy voninkazo'],
            'Quatre étudiantes se tiennent par les épaules devant la fenêtre de leur chambre' => ['Four female students with their arms around each other\'s shoulders in front of their room window', 'Mpianatra vavy efatra mifamihina eo amin\'ny sorony eo anoloan\'ny varavarankelin\'ny efitra misy azy'],
            'Deux diplômés du Master, souriants, en toge noire et capuche violette' => ['Two smiling Master\'s graduates in black gowns and purple hoods', 'Mpianatra roa nahazo ny Master, mitsiky, miaraka amin\'ny akanjo mainty sy satroka volomparasy'],
        ];

        foreach ($alts as $french => [$en, $mg]) {
            foreach (MediaAsset::where('alt', $french)->get() as $asset) {
                $this->fill($asset, 'en', ['alt' => $en]);
                $this->fill($asset, 'mg', ['alt' => $mg]);
            }
        }
    }

    /** @return list<array{type: string, data: array<string, mixed>}> Page « Confidentialité et cookies » : même structure de blocs que le français. */
    private function privacyBlocksInEnglish(): array
    {
        $block = fn (string $heading, string $html) => ['type' => 'rich_text', 'data' => ['heading' => $heading, 'content' => $html, 'width' => 'normal']];

        return [
            $block('In short', '<p>This site uses <strong>no advertising cookies, no audience-measurement tools and no social-network trackers</strong>. It only sets two technical cookies, essential to its operation, which therefore do not require a consent banner. The only personal data we collect is what you send us yourself through the contact form.</p>'),
            $block('Who is responsible for this data?', '<p>Zurcher Adventist University, PK 135, Route Nationale 7, Antsirabe, Madagascar. For any question about your data: <a href="mailto:office@zurcher.edu.mg">office@zurcher.edu.mg</a>.</p>'),
            $block('The cookies on this site', '<p>Two cookies are set during your visit:</p><ul><li><strong>universite-adventiste-zurcher-session</strong>: links your actions during the visit (for example sending the contact form). It disappears after 2 hours.</li><li><strong>XSRF-TOKEN</strong>: protects forms against fraudulent submissions. Same lifetime.</li></ul><p>They are not used to track you from one visit to the next, for statistics or for advertising. They contain no information identifying you. You can refuse them in your browser settings: the site remains readable, but the contact form may stop working.</p>'),
            $block('Your browser\'s memory', '<p>Without any cookie, the site keeps in your browser (local storage) <strong>a single piece of information</strong>: whether the newsletter sign-up window has already been offered to you, and when. It is only used so as not to offer it again on every page: you see it at most once every 20 days, as long as you are not subscribed. This information stays on your device and is never sent to the site; you can erase it by clearing the site data in your browser.</p>'),
            $block('The newsletter', '<p>If you subscribe, we record <strong>your email address</strong>, the date of your request and the date of your confirmation. We first send you a confirmation email: without a click on the link, you receive nothing and nobody can sign you up in your place. Every message contains a link to unsubscribe in one click. Your address is only used for this newsletter.</p>'),
            $block('The contact form', '<p>When you write to us, we receive the information you enter: <strong>name, email address, phone number (optional), subject and message</strong>. It is only used to reply to you. Your message is sent by email to UAZ and kept in the site\'s administration area, which only authorised people can access.</p><p>To limit automated messages (spam), we also record an <strong>encrypted, irreversible fingerprint of your IP address</strong> (the address itself is not kept) and the name of your browser.</p>'),
            $block('External services', '<ul><li><strong>Footer weather</strong>: retrieved by our server from Open-Meteo. Your browser does not contact this service.</li><li><strong>Map on the Contact page</strong>: provided by OpenStreetMap. Displaying it sends it your IP address, like any embedded content; we add no cookie to it.</li><li><strong>Links to other sites</strong> (Facebook, Instagram, LinkedIn, YouTube, TikTok, Registrar, Moodle, Alumni): these are simple links. Once on those sites, their own rules apply.</li></ul>'),
            $block('Your rights', '<p>You can ask us for access to the information you sent us, its correction or its deletion, by writing to <a href="mailto:office@zurcher.edu.mg">office@zurcher.edu.mg</a>.</p>'),
            $block('Last update', '<p>Last updated: 28 October 2026. This page is changed if the site changes the way it uses cookies or data (for example by adding a statistics tool).</p>'),
        ];
    }
}
