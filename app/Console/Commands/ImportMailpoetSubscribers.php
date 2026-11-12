<?php

namespace App\Console\Commands;

use App\Support\MailpoetImport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('newsletter:import-mailpoet {file : Export CSV de MailPoet} {--dry-run : Affiche le résultat sans rien enregistrer}')]
#[Description('Importe un export CSV de MailPoet dans les abonnés de la newsletter (mêmes règles que le bouton du back-office)')]
class ImportMailpoetSubscribers extends Command
{
    public function handle(): int
    {
        try {
            $result = MailpoetImport::run((string) $this->argument('file'), (bool) $this->option('dry-run'));
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach (MailpoetImport::summary($result, (bool) $this->option('dry-run')) as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }
}
