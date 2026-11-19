<?php

namespace App\Console\Commands;

use App\Services\IpGeolocation;
use App\Support\MaxMindDb\Reader;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Télécharge la base de géolocalisation « DB-IP City Lite » (gratuite, publiée chaque mois, sans clé ni compte) dans storage/app/geoip.
 * Ne fait rien si la base a moins de 25 jours (sauf --force) : planifiée chaque nuit, elle ne télécharge donc qu'une fois par mois.
 * Le fichier est vérifié (lu avec une adresse connue) avant de remplacer l'ancien, qui reste en place en cas d'échec.
 */
#[Signature('geoip:update {--force : Télécharger même si la base est récente}')]
#[Description('Télécharge ou met à jour la base de géolocalisation des visiteurs (DB-IP City Lite)')]
class UpdateGeoipDatabase extends Command
{
    private const MAX_AGE_DAYS = 25;

    public function handle(): int
    {
        $path = IpGeolocation::path();

        if (! $this->option('force') && is_file($path) && filemtime($path) > now()->subDays(self::MAX_AGE_DAYS)->getTimestamp()) {
            $this->info('Base de géolocalisation à jour.');

            return self::SUCCESS;
        }

        @mkdir(dirname($path), 0775, true);
        $archive = $path.'.gz.tmp';
        $unpacked = $path.'.tmp';

        foreach ([0, 1, 2] as $monthsAgo) {
            $month = now()->startOfMonth()->subMonths($monthsAgo)->format('Y-m');
            $url = "https://download.db-ip.com/free/dbip-city-lite-{$month}.mmdb.gz";

            try {
                $response = Http::timeout(900)->withOptions(['sink' => $archive])->get($url);
            } catch (Throwable $e) {
                $this->warn("Téléchargement impossible ({$month}) : ".$e->getMessage());

                continue;
            }

            if (! $response->successful()) {
                $this->line("Pas de fichier pour {$month} (HTTP {$response->status()}).");

                continue;
            }

            try {
                $this->unpack($archive, $unpacked);
                $this->check($unpacked);
            } catch (Throwable $e) {
                $this->error("Fichier {$month} inutilisable : ".$e->getMessage());
                @unlink($archive);
                @unlink($unpacked);

                return self::FAILURE;
            }

            @unlink($archive);
            rename($unpacked, $path);
            IpGeolocation::reset();
            $this->info("Base de géolocalisation {$month} installée (".round(filesize($path) / 1048576).' Mo).');

            return self::SUCCESS;
        }

        @unlink($archive);
        $this->error('Aucune base trouvée pour les trois derniers mois : l\'ancienne est conservée.');

        return self::FAILURE;
    }

    private function unpack(string $archive, string $target): void
    {
        $in = gzopen($archive, 'rb');
        $out = fopen($target, 'wb');

        if ($in === false || $out === false) {
            throw new \RuntimeException('lecture ou écriture impossible');
        }

        while (! gzeof($in)) {
            fwrite($out, (string) gzread($in, 1 << 20));
        }

        gzclose($in);
        fclose($out);
    }

    private function check(string $file): void
    {
        $reader = new Reader($file);
        $record = $reader->get('8.8.8.8');
        $reader->close();

        if (! is_array($record) || empty($record['country']['iso_code'])) {
            throw new \RuntimeException('la base ne répond pas à une adresse connue');
        }
    }
}
