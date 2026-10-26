<?php

namespace App\Console\Commands;

use App\Support\RedirectCsvImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('redirects:import {file=docs/URL-MAP.csv : Fichier CSV (ancienne_url, nouvelle_url, code, type, note)}')]
#[Description('Importe les redirections 301 depuis docs/URL-MAP.csv (idempotent)')]
class ImportRedirects extends Command
{
    public function handle(RedirectCsvImporter $importer): int
    {
        $file = base_path($this->argument('file'));
        $stats = $importer->import(is_file($file) ? $file : $this->argument('file'));

        $this->info("Redirections : {$stats['created']} créée(s), {$stats['updated']} mise(s) à jour, {$stats['skipped']} ignorée(s).");

        return self::SUCCESS;
    }
}
