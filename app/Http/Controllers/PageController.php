<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Faculty;
use App\Models\Formation;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\Project;
use App\Models\TeamMember;
use App\Support\FeeCatalog;
use App\Support\TimetableSheets;
use App\Support\Translated;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

/** Pages libres du back-office, servies par leur chemin complet (ex. admissions/licence). */
class PageController extends Controller
{
    public function __invoke(string $path): View|RedirectResponse
    {
        $page = Page::query()->visible()->where('path', trim($path, '/'))->with(['cover.media', 'ogImage.media', 'parent'])->firstOrFail();

        if ($page->key === 'home') {
            return redirect('/', 301);
        }

        // Fil d'Ariane : ancêtres puis page courante.
        $trail = [];
        for ($parent = $page->parent; $parent; $parent = $parent->parent) {
            array_unshift($trail, ['label' => $parent->title, 'url' => $parent->url()]);
        }
        $trail[] = ['label' => $page->title];

        // La page « À propos » a sa propre mise en page éditoriale (vidéo, histoire, campus…).
        if ($page->key === 'about') {
            return view('pages.about', [
                'page' => $page,
                'events' => Event::visible()->upcoming()->with('cover.media')->orderBy('starts_at')->limit(3)->get(),
            ]);
        }

        // « Soutenir l'UAZ » : directement vers le site des dons s'il est défini (anciens liens et favoris compris).
        if ($page->key === 'support' && filled(config('uaz.donation_url'))) {
            return redirect()->away(config('uaz.donation_url'));
        }

        // « Soutenir l'UAZ » : page dédiée aux dons et aux projets.
        if ($page->key === 'support') {
            return view('pages.support', [
                'page' => $page,
                'projects' => Project::active()->with('cover.media')->get(),
            ]);
        }

        // Bibliothèque Paul Pichot : mise en page immersive. Les deux images du texte importé (enseigne, affiche des horaires)
        // sont reprises dans l'ordre ; la salle de lecture est l'image de couverture de la page (champ « Image » du back-office).
        if ($page->key === 'student-life-library') {
            preg_match_all('/data-media="(\d+)"/', collect($page->blocks)->pluck('data.content')->implode(' '), $found);
            $media = MediaAsset::query()->with('media')->whereIn('id', $found[1])->get()->keyBy('id');

            return view('pages.library', [
                'page' => $page,
                'trail' => $trail,
                'hoursPoster' => $media->get((int) ($found[1][1] ?? 0)),
                'interior' => $page->cover ?? $media->get((int) ($found[1][0] ?? 0)),
            ]);
        }

        // Cafétéria : mise en page immersive. Les trois photos du texte importé sont reprises dans l'ordre (service au buffet, cuisine, équipe).
        if ($page->key === 'student-life-cafeteria') {
            preg_match_all('/data-media="(\d+)"/', collect($page->blocks)->pluck('data.content')->implode(' '), $found);
            $media = MediaAsset::query()->with('media')->whereIn('id', $found[1])->get()->keyBy('id');

            return view('pages.cafeteria', [
                'page' => $page,
                'trail' => $trail,
                'service' => $media->get((int) ($found[1][0] ?? 0)),
                'kitchen' => $media->get((int) ($found[1][1] ?? 0)),
                'team' => $media->get((int) ($found[1][2] ?? 0)),
            ]);
        }

        // Centre de recherche : mise en page immersive bâtie sur les seuls faits publiés (l'équipe du centre, le domaine de son directeur, la
        // présentation des parcours). Les blocs de la page ajoutés dans le back-office s'affichent à la fin ; ceux qui portent encore la
        // mention TODO-CONTENU (texte d'attente à l'usage de l'équipe) ne sont jamais montrés au public.
        if ($page->key === 'research') {
            $members = TeamMember::active()->with('photo.media')->where('job_title', 'like', '%Centre de recherche%')->get()
                ->sortBy(fn ($member) => str_starts_with(mb_strtolower($member->getRawOriginal('job_title')), 'directeur') ? 0 : 1)->values();

            // Le domaine est celui que nomme la fonction du directeur (« … / Responsable domaine Sciences Sociales »).
            $director = $members->first(fn ($member) => str_starts_with(mb_strtolower($member->getRawOriginal('job_title')), 'directeur'));
            $domain = $director
                ? Faculty::query()->where('is_active', true)->get()
                    ->first(fn ($faculty) => str_contains(mb_strtolower($director->getRawOriginal('job_title')), mb_strtolower($faculty->getRawOriginal('name'))))
                : null;
            $domain?->load(['formations' => fn ($query) => $query->visible()->orderBy('sort_order')->orderBy('title')]);

            return view('pages.research', [
                'page' => $page,
                'trail' => $trail,
                'members' => $members,
                'domain' => $domain,
                'blocks' => collect($page->blocks)->reject(fn ($block) => str_contains(json_encode($block, JSON_UNESCAPED_UNICODE), 'TODO-CONTENU'))->values(),
            ]);
        }

        // Système LMD : mise en page immersive (données : config/lmd.php, reprises du texte de la page). Chaque mention de Licence renvoie à sa fiche
        // de formation quand elle est publiée.
        if ($page->key === 'lmd') {
            $plain = fn (string $text) => trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower(Str::ascii($text))));
            $formations = Formation::visible()->get()->keyBy(fn ($formation) => $plain((string) $formation->getRawOriginal('mention')));

            return view('pages.lmd', [
                'page' => $page,
                'trail' => $trail,
                'data' => Translated::deep(config('lmd')),
                'raw' => config('lmd'),
                'links' => collect(config('lmd.licence_mentions'))->map(fn (array $mention) => $formations->get($plain((string) $mention['formation']))?->url())->all(),
            ]);
        }

        // Association des étudiants : mise en page immersive (textes : lang/*/association.php, tirés du Manuel de l'étudiant, section 3.3).
        // Les lettres « SA » du héros sont remplies d'une vraie photo du campus : la couverture (ou la première photo du texte) de la première
        // page de la vie étudiante qui en a une.
        if ($page->key === 'student-life-association') {
            $siblings = Page::visible()->whereIn('key', ['student-life-library', 'student-life-cafeteria', 'student-life-dorms'])->with('cover.media')->get()
                ->sortBy(fn ($sibling) => array_search($sibling->key, ['student-life-library', 'student-life-cafeteria', 'student-life-dorms']))->values();
            $photo = null;
            foreach ($siblings as $sibling) {
                $photo = $sibling->cover;
                if (! $photo && preg_match('/data-media="(\d+)"/', collect($sibling->blocks)->pluck('data.content')->implode(' '), $found)) {
                    $photo = MediaAsset::with('media')->find((int) $found[1]);
                }
                if ($photo?->file()) {
                    break;
                }
                $photo = null;
            }

            return view('pages.association', [
                'page' => $page,
                'trail' => $trail,
                'photo' => $photo,
                'downloads' => Page::visible()->where('key', 'admissions-downloads')->first(),
                'siblings' => $siblings,
            ]);
        }

        // Emplois du temps : une fiche par PDF saisi dans le texte de la page (nom, faculté et taille tirés des données réelles) ; ce qui reste
        // du texte (titres, explications) s'affiche à la suite.
        if ($page->key === 'timetables') {
            return view('pages.timetables', [
                'page' => $page,
                'trail' => $trail,
                'sheets' => TimetableSheets::from($page),
                'blocks' => TimetableSheets::remainder($page),
            ]);
        }

        // Dortoirs : mise en page immersive. Photos du texte dans l'ordre (allée, résidence des filles, résidence des garçons, chambre, puis cinq vues de chambres) ;
        // les deux liens Facebook (« Open House », « MJA ») sont repris tels qu'ils figurent dans le texte.
        if ($page->key === 'student-life-dorms') {
            $content = collect($page->blocks)->pluck('data.content')->implode(' ');
            preg_match_all('/data-media="(\d+)"/', $content, $found);
            preg_match_all('/<a href="(https:\/\/[^"]+)">([^<]+)<\/a>/', $content, $anchors, PREG_SET_ORDER);
            $media = MediaAsset::query()->with('media')->whereIn('id', $found[1])->get()->keyBy('id');

            return view('pages.dormitories', [
                'page' => $page,
                'trail' => $trail,
                'path' => $media->get((int) ($found[1][0] ?? 0)),
                'girls' => $media->get((int) ($found[1][1] ?? 0)),
                'boys' => $media->get((int) ($found[1][2] ?? 0)),
                // Photos de chambres (4e figure : héros ; 5e à 9e : mur de photos). Absentes : le héros reprend l'allée, le mur disparaît.
                'room' => $media->get((int) ($found[1][3] ?? 0)),
                'wall' => collect(array_slice($found[1], 4, 5))->map(fn ($id) => $media->get((int) $id))->filter()->values(),
                'links' => collect($anchors)->map(fn (array $a) => [$a[1], $a[2]])->all(),
                'project' => Project::active()->where('slug', 'un-grand-dortoir-pour-les-garcons')->first(),
            ]);
        }

        // Admission Licence / Master : mise en page immersive (données : config/admissions.php ; l'affiche officielle est la figure du texte importé).
        if (in_array($page->key, ['admissions-licence', 'admissions-master'], true)) {
            $level = $page->key === 'admissions-licence' ? 'licence' : 'master';
            $content = collect($page->blocks)->pluck('data.content')->implode(' ');
            preg_match_all('/data-media="(\\d+)"/', $content, $found);
            preg_match_all('/<a href="(https:\\/\\/[^"]+\\.docx?)"/i', $content, $docs);
            $poster = MediaAsset::query()->with('media')->find((int) ($found[1][0] ?? 0));

            return view('pages.admissions', [
                'page' => $page,
                'trail' => $trail,
                'level' => $level,
                'data' => Translated::deep(config('admissions.levels.'.$level)),
                'common' => Translated::deep(config('admissions')),
                'poster' => $poster,
                'forms' => collect(Translated::deep(config('admissions.levels.'.$level.'.downloads')))->zip(array_values(array_unique($docs[1])))->filter(fn ($pair) => $pair->count() === 2 && $pair[1])->values()->all(),
                'formations' => Formation::visible()->where('level', $level)->get()->keyBy(fn ($formation) => $formation->getRawOriginal('mention')), // clé = mention en français, quelle que soit la langue
            ]);
        }

        // Frais académiques : simulateur et tableaux (données : config/fees.php). Les tableaux officiels sont les figures du texte importé, dans l'ordre.
        if ($page->key === 'admissions-fees') {
            $content = collect($page->blocks)->pluck('data.content')->implode(' ');
            preg_match_all('/data-media="(\d+)"/', $content, $found);
            $media = MediaAsset::query()->with('media')->whereIn('id', $found[1])->get()->keyBy('id');

            return view('pages.fees', [
                'page' => $page,
                'trail' => $trail,
                'fees' => Translated::deep(config('fees')),
                'catalog' => Translated::deep(FeeCatalog::catalog()),
                'posters' => collect($found[1])->map(fn ($id) => $media->get((int) $id))->filter()->values(),
            ]);
        }

        return view('pages.show', [
            'page' => $page,
            'trail' => $trail,
            'children' => $page->children()->visible()->with('cover.media')->get(),
        ]);
    }
}
