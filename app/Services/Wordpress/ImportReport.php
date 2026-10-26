<?php

namespace App\Services\Wordpress;

/** Compte rendu d'un import : éléments créés / mis à jour / ignorés, échecs et contenus à relire à la main. */
class ImportReport
{
    /** @var array<string, array{created: int, updated: int, skipped: int, failed: int}> */
    public array $counts = [];

    /** @var list<array{type: string, ref: string, reason: string}> */
    public array $failures = [];

    /** @var list<array{type: string, ref: string, reason: string}> */
    public array $review = [];

    /** @var list<array{type: string, ref: string, reason: string}> */
    public array $notices = [];

    public function __construct(public bool $dryRun = false, public string $baseUrl = '') {}

    public function count(string $type, string $what): void
    {
        $this->counts[$type] ??= ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        $this->counts[$type][$what]++;
    }

    public function fail(string $type, string $ref, string $reason): void
    {
        $this->count($type, 'failed');
        $this->failures[] = compact('type', 'ref', 'reason');
    }

    /** Contenu importé mais à relire manuellement. */
    public function review(string $type, string $ref, string $reason): void
    {
        $this->review[] = compact('type', 'ref', 'reason');
    }

    /** Information (élément volontairement non importé, etc.). */
    public function notice(string $type, string $ref, string $reason): void
    {
        $this->notices[] = compact('type', 'ref', 'reason');
    }

    public function toMarkdown(): string
    {
        $labels = [
            'user' => 'Auteurs', 'media' => 'Médias', 'page' => 'Pages', 'post' => 'Articles',
            'team' => 'Équipe', 'testimonial' => 'Témoignages', 'redirect' => 'Redirections',
        ];

        $md = "# Rapport d'import WordPress\n\n";
        $md .= '- Source : '.($this->baseUrl ?: '—')."\n";
        $md .= '- Généré le : '.now()->format('d/m/Y H:i')."\n";
        $md .= '- Mode : '.($this->dryRun ? '**simulation (--dry-run)** : rien n\'a été écrit' : 'import réel')."\n\n";

        $md .= "## Résumé\n\n| Type | Créés | Mis à jour | Ignorés | Échecs |\n| --- | ---: | ---: | ---: | ---: |\n";
        foreach ($this->counts as $type => $c) {
            $md .= sprintf("| %s | %d | %d | %d | %d |\n", $labels[$type] ?? $type, $c['created'], $c['updated'], $c['skipped'], $c['failed']);
        }

        $md .= $this->section('Échecs', $this->failures, 'Aucun échec.');
        $md .= $this->section('À relire manuellement', $this->review, 'Rien de particulier.');
        $md .= $this->section('Informations', $this->notices, 'Aucune.');

        return $md;
    }

    /** @param  list<array{type: string, ref: string, reason: string}>  $items */
    private function section(string $title, array $items, string $empty): string
    {
        $md = "\n## $title\n\n";

        if ($items === []) {
            return $md."$empty\n";
        }

        foreach (collect($items)->groupBy('type') as $type => $group) {
            $md .= "### $type ({$group->count()})\n\n";
            foreach ($group as $item) {
                $md .= "- **{$item['ref']}** — {$item['reason']}\n";
            }
            $md .= "\n";
        }

        return $md;
    }
}
