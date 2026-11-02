<?php

namespace App\Console\Commands;

use App\Support\Locales;
use App\Support\TranslationKeys;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('i18n:check {locale? : en ou mg (toutes les langues si omis)} {--list : affiche les phrases manquantes}')]
#[Description('Liste les textes du site qui n\'ont pas encore de traduction (lang/{langue}.json et lang/{langue}/*.php)')]
class CheckTranslations extends Command
{
    public function handle(): int
    {
        $locales = $this->argument('locale') ? [$this->argument('locale')] : Locales::prefixed();
        $used = TranslationKeys::used();
        $this->info(count($used).' phrases à traduire dans les vues et le code.');
        $status = self::SUCCESS;

        foreach ($locales as $locale) {
            $missing = TranslationKeys::missing($locale);
            $groups = 0;
            foreach (TranslationKeys::groups() as $group) {
                $groups += count(array_diff_key(TranslationKeys::group('fr', $group), TranslationKeys::group($locale, $group)));
            }

            $this->line(sprintf('%s : %d phrase(s) sans traduction, %d texte(s) éditorial(aux) sans traduction.', $locale, count($missing), $groups));
            if ($this->option('list')) {
                foreach ($missing as $key) {
                    $this->line('  - '.$key);
                }
            }
            if ($missing || $groups) {
                $status = self::FAILURE;
            }
        }

        return $status;
    }
}
