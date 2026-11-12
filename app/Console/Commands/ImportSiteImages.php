<?php

namespace App\Console\Commands;

use App\Models\Faculty;
use App\Models\Formation;
use App\Models\ImportRecord;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\Project;
use App\Models\SiteSetting;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Throwable;

#[Signature('media:import-site-images {--from=https://zurcher.edu.mg : Adresse du site actuel} {--only= : Ne traiter que les photos dont le chemin contient ce texte} {--dry-run : Affiche ce qui serait fait sans rien écrire}')]
#[Description('Importe dans la médiathèque les photos du site actuel (zurcher.edu.mg) et les place sur les formations, domaines, projets et pages qui n\'ont pas d\'image')]
class ImportSiteImages extends Command
{
    /** Source d'import : une photo n'est téléchargée qu'une fois, même si la commande est relancée. */
    private const SOURCE = 'site-image';

    /**
     * Les photos retenues : chemin sur le site actuel => titre, texte alternatif (ce que la photo montre vraiment : les noms de fichiers du
     * site actuel ne sont pas toujours fidèles) et contenus à illustrer. Un contenu qui a déjà une image n'est jamais modifié.
     *
     * @return array<string, array{title: string, alt: string, to: list<array{0: string, 1: string}>}>
     */
    private function manifest(): array
    {
        return [
            '/images/site/hero-diplomes.jpg' => [
                'title' => 'Diplômés de l\'UAZ',
                'alt' => 'Des diplômés de l\'UAZ en toge et toque, souriants, dans le parc du campus',
                'to' => [['page', 'systeme-lmd']],
            ],
            '/images/site/admission-licence.jpg' => [
                'title' => 'Diplômés de la Licence',
                'alt' => 'Trois diplômés de l\'UAZ en toge noire et étole jaune, tenant leur diplôme',
                'to' => [['page', 'admissions'], ['setting', 'default_og_image_id']],
            ],
            '/images/site/sciences-infirmieres.jpg' => [
                'title' => 'Département de Sciences infirmières',
                'alt' => 'Les étudiants en blouse blanche du département de Sciences infirmières réunis dans une salle',
                'to' => [['formation', 'licence-en-sciences-infirmieres']],
            ],
            '/images/site/etudes-anglophones.jpg' => [
                'title' => 'Étudiants du département d\'Études anglophones',
                'alt' => 'Une chorale d\'étudiants en chemise blanche, devant une tenture bleue, face à l\'assemblée',
                'to' => [['formation', 'licence-en-etudes-anglophones']],
            ],
            '/images/actualites/homecoming.jpg' => [
                'title' => 'Prédication au Homecoming',
                'alt' => 'Une oratrice, micro en main et bras levé, devant un texte biblique projeté',
                'to' => [['formation', 'licence-en-theologie']],
            ],
            '/images/actualites/rencontre-futurs-etudiants.jpg' => [
                'title' => 'À la rencontre des futurs étudiants',
                'alt' => 'Un membre de l\'UAZ remet une brochure à une visiteuse lors d\'un salon d\'orientation',
                'to' => [['formation', 'licence-en-communication']],
            ],
            '/images/site/plein-air.jpg' => [
                'title' => 'Étudiants en plein air',
                'alt' => 'Des étudiants assis ensemble sous les pins du campus, en pleine discussion',
                'to' => [['formation', 'licence-en-gestion']],
            ],
            '/images/site/soutenir-projet.jpg' => [
                'title' => 'Soutenance de mémoires',
                'alt' => 'Quatre étudiants tenant leur mémoire relié, entourés de deux enseignants',
                'to' => [['formation', 'licence-en-droit']],
            ],
            '/images/site/chambre-2.jpg' => [
                'title' => 'Étudiants au travail dans leur chambre',
                'alt' => 'Deux étudiants travaillant sur leurs ordinateurs portables à une table de leur chambre',
                'to' => [['formation', 'licence-en-informatique']],
            ],
            '/images/site/communaute.jpg' => [
                'title' => 'La communauté étudiante en tenues traditionnelles',
                'alt' => 'Un groupe d\'étudiants et d\'enseignants en tenues malgaches colorées, posant sous les arbres du campus',
                'to' => [['faculty', 'arts-lettres'], ['page', 'vie-etudiante']],
            ],
            '/images/site/etudiants-banc.jpg' => [
                'title' => 'Étudiants sur les gradins du campus',
                'alt' => 'Des étudiants assis sur des gradins, à l\'ombre des pins du campus',
                'to' => [['faculty', 'sciences-sociales'], ['page', 'emplois-du-temps']],
            ],
            '/images/actualites/centre-medical-zurcher.jpg' => [
                'title' => 'Inauguration du Centre Médical Zurcher',
                'alt' => 'Des autorités coupant le ruban lors de l\'inauguration du Centre Médical Zurcher',
                'to' => [['faculty', 'sciences-de-la-sante']],
            ],
            '/images/site/chambre-1.jpg' => [
                'title' => 'Étudiants devant leurs ordinateurs',
                'alt' => 'Un groupe d\'étudiants réunis autour d\'ordinateurs dans une chambre de la résidence',
                'to' => [['faculty', 'sciences-de-lingenierie']],
            ],
            '/images/site/dortoir-r2-facade.jpg' => [
                'title' => 'Résidence des garçons',
                'alt' => 'La résidence des garçons au milieu des pins, son panneau d\'identification au premier plan',
                'to' => [['project', 'un-grand-dortoir-pour-les-garcons']],
            ],
            '/images/site/campus-drone.jpg' => [
                'title' => 'Le campus vu du ciel au coucher du soleil',
                'alt' => 'Vue aérienne du campus de l\'UAZ au coucher du soleil, avec ses toits rouges au milieu des arbres',
                'to' => [['project', 'des-logements-pour-le-personnel']],
            ],
            '/images/site/campus-drapeaux.jpg' => [
                'title' => 'Les drapeaux du campus',
                'alt' => 'Les drapeaux de Madagascar et de l\'UAZ dressés au milieu d\'un parterre, parmi les pins',
                'to' => [['page', 'admissions/frais-academiques']],
            ],
            '/images/site/campus-panneau.jpg' => [
                'title' => 'Le panneau d\'accueil du campus',
                'alt' => 'Le panneau « Votre futur commence ici », au centre d\'un massif, sous les pins',
                'to' => [['page', 'admissions/telechargements']],
            ],
            '/images/site/cafeteria-salle.jpg' => [
                'title' => 'Le service à la cafétéria',
                'alt' => 'Des convives se servant au buffet de la cafétéria',
                'to' => [['page', 'vie-etudiante/cafeteria']],
            ],
            '/images/site/dortoirs-batiment.jpg' => [
                'title' => 'Une chambre de la résidence',
                'alt' => 'Une chambre d\'étudiant avec lits superposés, table fleurie et armoire en bois',
                'to' => [['page', 'vie-etudiante/dortoirs']],
            ],
            '/images/site/etudiants.jpg' => [
                'title' => 'Étudiants de l\'UAZ en sweat à l\'écusson',
                'alt' => 'Une étudiante et un étudiant portant le sweat gris à l\'écusson de l\'UAZ devant un bâtiment du campus',
                'to' => [['page', 'vie-etudiante/association-des-etudiants']],
            ],
            '/images/site/bibliotheque.jpg' => [
                'title' => 'La bibliothèque de l\'UAZ',
                'alt' => 'La salle de lecture de la bibliothèque, avec ses rayonnages et ses tables rondes',
                'to' => [['page', 'centre-de-recherche']],
            ],
            '/images/actualites/culte-ouverture-2026.jpg' => [
                'title' => 'Culte d\'ouverture de l\'année académique 2026-2027',
                'alt' => 'Des étudiants en toge et toque assistant au culte d\'ouverture de l\'année académique',
                'to' => [['page', 'culte-douverture-de-lannee-academique-2026-2027-une-nouvelle-page-souvre-a']],
            ],
        ];
    }

    public function handle(): int
    {
        $base = rtrim((string) $this->option('from'), '/');
        $dry = (bool) $this->option('dry-run');
        $stats = ['imported' => 0, 'known' => 0, 'placed' => 0, 'kept' => 0, 'missing' => 0, 'failed' => 0];

        $only = (string) $this->option('only');

        foreach ($this->manifest() as $path => $item) {
            if ($only !== '' && ! str_contains($path, $only)) {
                continue;
            }

            $asset = $this->asset($base, $path, $item, $dry, $stats);

            foreach ($item['to'] as [$kind, $key]) {
                $target = $this->target($kind, $key);

                if (! $target) {
                    $stats['missing']++;
                    $this->warn("  contenu introuvable : $kind « $key »");

                    continue;
                }

                $column = $this->column($kind, $key);

                if ($target->getAttribute($column) !== null) {
                    $stats['kept']++;

                    continue;
                }

                if (! $dry && $asset) {
                    $target->forceFill([$column => $asset->getKey()])->save();
                }

                $stats['placed']++;
                $this->line("  → $kind « $key »".($dry ? ' (simulation)' : ''));
            }
        }

        $this->newLine();
        $this->info("Photos importées : {$stats['imported']} (déjà présentes : {$stats['known']}) · placées : {$stats['placed']} · déjà illustrées, laissées telles quelles : {$stats['kept']} · contenus introuvables : {$stats['missing']} · échecs : {$stats['failed']}");

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** La photo de la médiathèque : celle déjà importée, sinon téléchargée depuis le site actuel. */
    private function asset(string $base, string $path, array $item, bool $dry, array &$stats): ?MediaAsset
    {
        $record = ImportRecord::lookup(self::SOURCE, $path);

        if ($record && ($asset = MediaAsset::find($record->model_id))) {
            $stats['known']++;

            return $asset;
        }

        $this->line($path);

        if ($dry) {
            $stats['imported']++;

            return null;
        }

        try {
            $response = Http::timeout(60)->retry(2, 500)->get($base.$path);

            if (! $response->successful() || ! str_starts_with((string) $response->header('Content-Type'), 'image/')) {
                throw new \RuntimeException('réponse '.$response->status());
            }

            $tmp = tempnam(sys_get_temp_dir(), 'uazimg').'.'.pathinfo($path, PATHINFO_EXTENSION);
            file_put_contents($tmp, $response->body());

            $asset = MediaAsset::create(['title' => $item['title'], 'alt' => $item['alt'], 'credit' => 'Université Adventiste Zurcher']);
            $asset->addMedia($tmp)->usingFileName(basename($path))->toMediaCollection(MediaAsset::COLLECTION);

            ImportRecord::remember(self::SOURCE, $path, $asset, null, $base.$path);
            $stats['imported']++;

            return $asset;
        } catch (Throwable $e) {
            $stats['failed']++;
            $this->error("  échec : {$e->getMessage()}");

            return null;
        }
    }

    private function target(string $kind, string $key): ?Model
    {
        return match ($kind) {
            'formation' => Formation::where('slug', $key)->first(),
            'faculty' => Faculty::where('slug', $key)->first(),
            'project' => Project::where('slug', $key)->first(),
            'page' => Page::where('path', $key)->first(),
            'setting' => SiteSetting::current(),
            default => null,
        };
    }

    /** Colonne qui porte l'image du contenu. */
    private function column(string $kind, string $key): string
    {
        return $kind === 'setting' ? $key : 'cover_id';
    }
}
