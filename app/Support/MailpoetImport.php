<?php

namespace App\Support;

use App\Models\NewsletterSubscriber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Importe dans les abonnés de la newsletter un export CSV de MailPoet (extension WordPress de l'ancien site).
 *
 * Règles (le consentement des personnes prime sur la complétude de la liste) :
 *  - « subscribed »   → abonné actif (date de confirmation, à défaut date d'abonnement) ;
 *  - « unconfirmed »  → abonné actif aussi : l'abonnement sur le site est immédiat (aucune confirmation par e-mail), les adresses restées « en attente » dans MailPoet
 *                       sont donc reprises directement comme abonnées (décision de l'UAZ, voir D-074) ;
 *  - « unsubscribed » → enregistré comme désinscrit : la désinscription est conservée, la personne ne sera pas réinscrite par erreur ;
 *  - « bounced »      → ignoré (adresse qui n'existe plus ou refuse le courrier) ;
 *  - une adresse déjà désinscrite sur ce site n'est JAMAIS réinscrite par l'import ; une adresse déjà active n'est pas modifiée ;
 *  - adresses nettoyées (espaces, « ; » final, majuscules) puis validées ; les doublons du fichier ne comptent qu'une fois.
 * Les colonnes sont reconnues par leur intitulé (E-mail, État global, Temps d'abonnement, Temps de confirmation) : l'ordre importe peu.
 */
final class MailpoetImport
{
    /** @var list<string> Compteurs rendus, dans l'ordre d'affichage. */
    public const COUNTERS = ['active_added', 'unconfirmed_included', 'active_confirmed', 'already', 'unsubscribed_added', 'unsubscribed_marked', 'kept_unsubscribed', 'bounced', 'invalid', 'duplicates', 'unknown_status'];

    /**
     * @return array{total: int, counts: array<string, int>, invalid: list<string>}
     *
     * @throws \InvalidArgumentException fichier illisible ou sans colonne « E-mail »
     */
    public static function run(string $path, bool $dryRun = false): array
    {
        $rows = self::read($path);
        $counts = array_fill_keys(self::COUNTERS, 0);
        $invalid = [];
        $seen = [];

        $process = function () use ($rows, &$counts, &$invalid, &$seen, $dryRun): void {
            foreach ($rows as $row) {
                $email = self::cleanEmail($row['email']);

                if ($email === null) {
                    $counts['invalid']++;
                    $invalid[] = trim($row['email']);

                    continue;
                }

                if (isset($seen[$email])) {
                    $counts['duplicates']++;

                    continue;
                }
                $seen[$email] = true;

                $status = $row['status'];

                if ($status === 'bounced') {
                    $counts['bounced']++;

                    continue;
                }

                if (! in_array($status, ['subscribed', 'unconfirmed', 'unsubscribed'], true)) {
                    $counts['unknown_status']++;

                    continue;
                }

                self::apply($email, $status, $row, $counts, $dryRun);
            }
        };

        $dryRun ? $process() : DB::transaction($process);

        return ['total' => count($rows), 'counts' => $counts, 'invalid' => array_slice(array_values(array_unique($invalid)), 0, 10)];
    }

    /** @param  array<string, int>  $counts */
    private static function apply(string $email, string $status, array $row, array &$counts, bool $dryRun): void
    {
        $existing = NewsletterSubscriber::query()->where('email', $email)->first();
        $subscribedAt = self::date($row['subscribed_at']);
        $confirmedAt = self::date($row['confirmed_at']) ?? $subscribedAt ?? now();

        // « unconfirmed » : abonné comme les autres (l'abonnement est immédiat sur ce site, il n'y a plus de confirmation à attendre).
        if ($status === 'subscribed' || $status === 'unconfirmed') {
            if (! $existing) {
                $counts['active_added']++;
                $counts['unconfirmed_included'] += $status === 'unconfirmed' ? 1 : 0;
                $dryRun || self::create($email, $subscribedAt, ['confirmed_at' => $confirmedAt]);
            } elseif ($existing->unsubscribed_at !== null) {
                $counts['kept_unsubscribed']++; // désinscrit sur ce site : on ne réinscrit jamais
            } elseif ($existing->confirmed_at === null) {
                $counts['active_confirmed']++;
                $dryRun || $existing->forceFill(['confirmed_at' => $confirmedAt])->save();
            } else {
                $counts['already']++;
            }

            return;
        }

        // unsubscribed : la désinscription est conservée
        if (! $existing) {
            $counts['unsubscribed_added']++;
            $dryRun || self::create($email, $subscribedAt, ['unsubscribed_at' => now()]);
        } elseif ($existing->unsubscribed_at === null) {
            $counts['unsubscribed_marked']++;
            $dryRun || $existing->forceFill(['unsubscribed_at' => now()])->save();
        } else {
            $counts['already']++;
        }
    }

    /** @param  array<string, mixed>  $state */
    private static function create(string $email, ?Carbon $subscribedAt, array $state): void
    {
        $subscriber = new NewsletterSubscriber;
        $subscriber->forceFill(['email' => $email] + $state);

        if ($subscribedAt) {
            $subscriber->created_at = $subscribedAt; // « Demande du » : la vraie date d'abonnement
        }

        $subscriber->save();
    }

    /** Adresse nettoyée (espaces, ponctuation finale, majuscules) et validée ; null si elle n'est pas utilisable. */
    public static function cleanEmail(?string $value): ?string
    {
        $email = Str::lower(trim((string) $value, " \t\n\r\0\x0B;,"));

        return $email !== '' && strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private static function date(?string $value): ?Carbon
    {
        $value = trim((string) $value);

        try {
            return $value === '' ? null : Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<array{email: string, status: string, subscribed_at: ?string, confirmed_at: ?string}> */
    private static function read(string $path): array
    {
        $content = is_readable($path) ? file_get_contents($path) : false;

        if ($content === false || trim($content) === '') {
            throw new \InvalidArgumentException('Le fichier est introuvable ou vide.');
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252'); // export enregistré par Excel
        }

        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = collect([',', ';', "\t"])->sortByDesc(fn (string $d) => substr_count($firstLine, $d))->first();

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $header = fgetcsv($stream, 0, $delimiter, '"', '');
        $columns = [];

        foreach ((array) $header as $i => $title) {
            $columns[Str::of((string) $title)->ascii()->lower()->replaceMatches('/[^a-z]/', '')->toString()] = $i;
        }

        $emailAt = $columns['email'] ?? $columns['courriel'] ?? $columns['adressemail'] ?? null;

        if ($emailAt === null) {
            fclose($stream);

            throw new \InvalidArgumentException('Colonne « E-mail » introuvable : ce fichier ne ressemble pas à un export MailPoet.');
        }

        $statusAt = $columns['etatglobal'] ?? $columns['etatdelaliste'] ?? $columns['status'] ?? $columns['statut'] ?? null;
        $subscribedAt = $columns['tempsdabonnement'] ?? $columns['subscribedat'] ?? null;
        $confirmedAt = $columns['tempsdeconfirmation'] ?? $columns['confirmedat'] ?? null;

        $rows = [];

        while (($line = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            if ($line === [null] || ! isset($line[$emailAt])) {
                continue; // ligne vide
            }

            $rows[] = [
                'email' => (string) $line[$emailAt],
                'status' => Str::lower(trim((string) ($statusAt !== null ? ($line[$statusAt] ?? '') : 'subscribed'))),
                'subscribed_at' => $subscribedAt !== null ? ($line[$subscribedAt] ?? null) : null,
                'confirmed_at' => $confirmedAt !== null ? ($line[$confirmedAt] ?? null) : null,
            ];
        }

        fclose($stream);

        return $rows;
    }

    /** Résumé lisible d'un résultat (une phrase par ligne), pour la notification de l'administration et la commande. @return list<string> */
    public static function summary(array $result, bool $dryRun = false): array
    {
        $c = $result['counts'];
        $lines = [
            ($dryRun ? 'SIMULATION : rien n\'a été enregistré. ' : '').$result['total'].' ligne(s) lue(s).',
            $c['active_added'].' abonné(s) actif(s) ajouté(s)'.($c['unconfirmed_included'] ? ' (dont '.$c['unconfirmed_included'].' qui n\'avaient pas confirmé dans MailPoet)' : '').($c['active_confirmed'] ? ', '.$c['active_confirmed'].' adresse(s) déjà présente(s) mais non confirmée(s) désormais abonnée(s)' : '').'.',
        ];

        foreach ([
            'unsubscribed_added' => '%d désinscription(s) conservée(s)',
            'unsubscribed_marked' => '%d abonné(s) déjà présent(s) marqué(s) désinscrit(s)',
            'already' => '%d déjà présent(s), non modifié(s)',
            'kept_unsubscribed' => '%d déjà désinscrit(s) sur le site : non réinscrit(s)',
            'bounced' => '%d adresse(s) en rebond ignorée(s)',
            'invalid' => '%d adresse(s) invalide(s) ignorée(s)',
            'duplicates' => '%d doublon(s) ignoré(s)',
            'unknown_status' => '%d ligne(s) à l\'état inconnu ignorée(s)',
        ] as $key => $pattern) {
            if ($c[$key] > 0) {
                $lines[] = sprintf($pattern, $c[$key]).'.';
            }
        }

        if ($result['invalid']) {
            $lines[] = 'Adresses invalides : '.implode(', ', $result['invalid']).'.';
        }

        return $lines;
    }
}
