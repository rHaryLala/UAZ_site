<?php

namespace App\Support;

use App\Models\Redirect;
use InvalidArgumentException;

/**
 * Importe docs/URL-MAP.csv (ancienne_url, nouvelle_url, code, type, note) dans la table des redirections.
 * Idempotent : une ancienne URL existante est mise à jour. Les lignes à motif ({slug}, {terme}) sont ignorées
 * car gérées par le code (recherche) ou sans équivalent exact.
 */
class RedirectCsvImporter
{
    /** @return array{created: int, updated: int, skipped: int} */
    public function import(string $path): array
    {
        if (! is_readable($path)) {
            throw new InvalidArgumentException("Fichier illisible : $path");
        }

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, escape: '\\');

        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            $data = array_combine($header, array_pad($row, count($header), ''));
            $from = trim($data['ancienne_url'] ?? '');
            $to = trim($data['nouvelle_url'] ?? '');

            if ($from === '' || $to === '' || str_contains($from, '{') || str_contains($to, '{')
                || Redirect::normalize($from) === Redirect::normalize($to)) {
                $stats['skipped']++;

                continue;
            }

            $redirect = Redirect::firstOrNew(['from_path' => Redirect::normalize($from)]);
            $redirect->exists ? $stats['updated']++ : $stats['created']++;
            $redirect->fill([
                'to_path' => $to,
                'status_code' => (int) ($data['code'] ?: 301),
                'is_active' => true,
                'note' => $data['note'] ?: null,
            ])->save();
        }

        fclose($handle);

        return $stats;
    }
}
