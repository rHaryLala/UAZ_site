<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

#[Signature('db:import-sqlite {path : Fichier SQLite source} {--keep= : Tables à reprendre en plus de celles exclues par défaut, séparées par des virgules} {--keep-passwords : Conserve les mots de passe (sinon ils sont remplacés par des valeurs aléatoires)}')]
#[Description('Recopie le contenu d\'une base SQLite (poste de développement) dans la base configurée (MariaDB en production)')]
class ImportSqlite extends Command
{
    /** Tables propres à la machine d'origine : sessions, caches, files d'attente, et données d'essai (messages, abonnés, lettres). */
    private const SKIPPED = [
        'migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens',
        'contact_messages', 'newsletter_subscribers', 'newsletters', 'newsletter_deliveries',
    ];

    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error("Fichier introuvable : $path");

            return self::FAILURE;
        }

        config(['database.connections.import_source' => ['driver' => 'sqlite', 'database' => $path, 'foreign_key_constraints' => false]]);
        DB::purge('import_source');

        $source = DB::connection('import_source');
        $target = DB::connection();
        $keep = array_filter(array_map('trim', explode(',', (string) $this->option('keep'))));
        $skipped = array_diff(self::SKIPPED, $keep);
        $isMysql = in_array($target->getDriverName(), ['mysql', 'mariadb'], true);

        $tables = collect(Schema::connection('import_source')->getTableListing())
            ->map(fn ($table) => preg_replace('/^.*\./', '', $table))
            ->reject(fn ($table) => in_array($table, $skipped, true) || str_starts_with($table, 'sqlite_'))
            ->values();

        if ($isMysql) {
            $target->statement('SET FOREIGN_KEY_CHECKS=0');
        }

        $failed = false;

        try {
            foreach ($tables as $table) {
                if (! Schema::connection($target->getName())->hasTable($table)) {
                    $this->warn("  $table : absente de la base cible (ignorée)");

                    continue;
                }

                $columns = array_values(array_intersect(Schema::connection('import_source')->getColumnListing($table), Schema::connection($target->getName())->getColumnListing($table)));
                $target->table($table)->delete();
                $count = 0;

                $source->table($table)->orderByRaw('1')->chunk(200, function ($rows) use ($target, $table, $columns, &$count) {
                    $batch = $rows->map(fn ($row) => collect((array) $row)->only($columns)->all())->all();

                    if ($table === 'users' && ! $this->option('keep-passwords')) {
                        $batch = array_map(fn ($row) => array_merge($row, ['password' => Hash::make(Str::random(40)), 'remember_token' => null]), $batch);
                    }

                    $target->table($table)->insert($batch);
                    $count += count($batch);
                });

                $this->line(sprintf('  %-28s %5d ligne(s)', $table, $count));
            }
        } catch (Throwable $e) {
            $failed = true;
            $this->error('Import interrompu : '.$e->getMessage());
        } finally {
            if ($isMysql) {
                $target->statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }

        if (! $failed) {
            $this->info('Terminé.'.($this->option('keep-passwords') ? '' : ' Les mots de passe des comptes ont été remplacés par des valeurs aléatoires.'));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
