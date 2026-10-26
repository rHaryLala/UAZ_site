<?php

namespace App\Services\Wordpress;

use App\Models\ImportRecord;
use App\Models\MediaAsset;
use Throwable;

/**
 * Importe les médias WordPress dans la médiathèque : téléchargement, rattachement via Media Library
 * (compression et conversions automatiques), texte alternatif et légende conservés.
 * N'importe que les médias réellement référencés par le contenu importé (pas les 400+ fichiers orphelins).
 */
class MediaImporter
{
    /** @var array<int, array<string, mixed>> id WordPress → fiche média */
    private array $byId = [];

    /** @var array<string, int> URL (toutes tailles) → id WordPress */
    private array $byUrl = [];

    /** @var array<int, MediaAsset|null> cache de la session */
    private array $done = [];

    /** @var list<int> médias importés sans texte alternatif d'origine */
    public array $withoutAlt = [];

    public function __construct(
        private readonly WordpressClient $client,
        private readonly ImportReport $report,
        private readonly bool $dryRun,
    ) {}

    /** @param  list<array<string, mixed>>  $media */
    public function index(array $media): void
    {
        foreach ($media as $item) {
            $this->byId[$item['id']] = $item;
            $this->byUrl[$this->normalizeUrl($item['source_url'])] = $item['id'];

            foreach ($item['media_details']['sizes'] ?? [] as $size) {
                $this->byUrl[$this->normalizeUrl($size['source_url'])] = $item['id'];
            }
        }
    }

    public function count(): int
    {
        return count($this->byId);
    }

    /** @return list<int> */
    public function ids(): array
    {
        return array_keys($this->byId);
    }

    /** Identifiant WordPress d'un média d'après son URL (même redimensionnée), ou null. */
    public function idForUrl(string $url): ?int
    {
        $normalized = $this->normalizeUrl($url);

        if (isset($this->byUrl[$normalized])) {
            return $this->byUrl[$normalized];
        }

        // Variante « -300x200 » ou « -scaled » non listée : on retombe sur l'original.
        $original = preg_replace('/-(?:\d+x\d+|scaled)(\.[a-z0-9]+)$/i', '$1', $normalized);

        return $this->byUrl[$original] ?? null;
    }

    public function importByUrl(string $url): ?MediaAsset
    {
        $id = $this->idForUrl($url);

        return $id ? $this->import($id) : null;
    }

    public function import(int $wpId): ?MediaAsset
    {
        if (array_key_exists($wpId, $this->done)) {
            return $this->done[$wpId];
        }

        $item = $this->byId[$wpId] ?? null;

        if (! $item) {
            return $this->done[$wpId] = null;
        }

        $label = $item['slug'] ?? (string) $wpId;
        $record = ImportRecord::lookup('media', $wpId);

        if ($record && ($asset = MediaAsset::find($record->model_id))) {
            $this->report->count('media', 'skipped');

            return $this->done[$wpId] = $asset;
        }

        $mime = (string) ($item['mime_type'] ?? '');
        $maxKb = (int) config('uaz.media.max_file_size_kb');
        $sizeKb = (int) ceil(($item['media_details']['filesize'] ?? 0) / 1024);

        if (! in_array($mime, config('uaz.media.accepted_mime_types'), true)) {
            $this->report->count('media', 'skipped');
            $this->report->review('Médias', $label, "type non autorisé ($mime) : le lien pointe encore vers l'ancien site, à convertir en PDF ou en image");

            return $this->done[$wpId] = null;
        }

        if ($sizeKb > $maxKb) {
            $this->report->count('media', 'skipped');
            $this->report->review('Médias', $label, "trop volumineux ($sizeKb Ko pour {$maxKb} Ko maximum) : à alléger puis envoyer dans la médiathèque");

            return $this->done[$wpId] = null;
        }

        if ($this->dryRun) {
            $this->report->count('media', 'created');

            return $this->done[$wpId] = null;
        }

        try {
            $body = $this->client->download($item['source_url']);
            $tmp = tempnam(sys_get_temp_dir(), 'wpmedia').'.'.pathinfo(parse_url($item['source_url'], PHP_URL_PATH), PATHINFO_EXTENSION);
            file_put_contents($tmp, $body);

            $alt = trim((string) ($item['alt_text'] ?? ''));
            $title = html_entity_decode(strip_tags($item['title']['rendered'] ?? ''), ENT_QUOTES | ENT_HTML5);

            $asset = MediaAsset::create([
                'title' => $title ?: null,
                'alt' => $alt,
                'caption' => trim(html_entity_decode(strip_tags($item['caption']['rendered'] ?? ''), ENT_QUOTES | ENT_HTML5)) ?: null,
            ]);

            $asset->addMedia($tmp)
                ->usingFileName(basename(parse_url($item['source_url'], PHP_URL_PATH)))
                ->toMediaCollection(MediaAsset::COLLECTION);

            ImportRecord::remember('media', $wpId, $asset, md5($item['modified'] ?? ''), $item['source_url']);
            $this->report->count('media', 'created');

            if ($alt === '' && str_starts_with($mime, 'image/')) {
                $this->withoutAlt[] = $asset->id;
            }

            return $this->done[$wpId] = $asset;
        } catch (Throwable $e) {
            $this->report->fail('media', $label, $e->getMessage());

            return $this->done[$wpId] = null;
        }
    }

    /** URL comparable : sans schéma, sans paramètre, minuscule. */
    private function normalizeUrl(string $url): string
    {
        $parts = parse_url($url);

        return strtolower(preg_replace('/^www\./', '', $parts['host'] ?? '').($parts['path'] ?? ''));
    }
}
