<?php

namespace App\Console\Commands;

use App\Services\Wordpress\ImportReport;
use App\Services\Wordpress\WordpressClient;
use App\Services\Wordpress\WordpressImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('import:wordpress
    {--dry-run : Simule l\'import : aucune écriture en base, aucun téléchargement de média}
    {--only= : Étapes à exécuter, séparées par des virgules (users,pages,posts,team,testimonials,redirects,media)}
    {--force : Remplace aussi les contenus modifiés dans le back-office depuis le dernier import}
    {--with-avif : Génère aussi les conversions AVIF pendant l\'import (lent ; sinon : php artisan media-library:regenerate)}
    {--report= : Chemin du rapport Markdown (défaut : docs/IMPORT-REPORT.md ; rien en simulation)}')]
#[Description('Importe pages, articles, médias, équipe et témoignages depuis zurcher.edu.mg (lecture seule, idempotent)')]
class ImportWordpress extends Command
{
    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $only = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('only')))));
        $unknown = array_diff($only, [...WordpressImporter::STEPS, 'media']);

        if ($unknown) {
            $this->components->error('Étape(s) inconnue(s) : '.implode(', ', $unknown).'. Valeurs : '.implode(', ', [...WordpressImporter::STEPS, 'media']));

            return self::INVALID;
        }

        if (! $this->option('with-avif')) {
            // L'AVIF multiplie le temps de traitement des images ; il se génère ensuite avec media-library:regenerate.
            config(['uaz.media.avif' => false]);
        }

        $client = WordpressClient::fromConfig();
        $report = new ImportReport($dry, $client->baseUrl());
        $importer = new WordpressImporter($client, $report, $dry, (bool) $this->option('force'), base_path('docs/URL-MAP.csv'));

        $this->components->info(($dry ? 'Simulation' : 'Import').' depuis '.$client->baseUrl().' (lecture seule, 1 requête / '.config('uaz.wordpress.delay_ms').' ms)…');

        try {
            $importer->run($only);
        } catch (Throwable $e) {
            $this->components->error('Import interrompu : '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Type', 'Créés', 'Mis à jour', 'Ignorés', 'Échecs'], collect($report->counts)->map(
            fn ($c, $type) => [$type, $c['created'], $c['updated'], $c['skipped'], $c['failed']],
        )->values()->all());

        $this->line(sprintf('À relire : %d · Informations : %d · Échecs : %d', count($report->review), count($report->notices), count($report->failures)));

        $path = $this->option('report') ?: ($dry ? null : base_path('docs/IMPORT-REPORT.md'));
        if ($path) {
            file_put_contents($path, $report->toMarkdown());
            $this->components->info("Rapport écrit : $path");
        }

        if (! $dry && ! $this->option('with-avif')) {
            $this->components->warn('Pensez à lancer `php artisan media-library:regenerate` pour produire les variantes AVIF.');
        }

        return $report->failures ? self::FAILURE : self::SUCCESS;
    }
}
