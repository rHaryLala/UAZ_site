<?php

namespace App\Console\Commands;

use App\Models\Page;
use DOMDocument;
use DOMElement;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pages:downloads-list {--path=admissions/telechargements : Chemin de la page}')]
#[Description('Transforme la page « Téléchargements » importée de WordPress (titres + liens) en bloc « Liste de téléchargements » modifiable')]
class ConvertDownloadsPage extends Command
{
    public function handle(): int
    {
        $page = Page::where('path', $this->option('path'))->first();

        if (! $page) {
            $this->error('Page introuvable.');

            return self::FAILURE;
        }

        if (collect($page->blocks)->contains(fn ($b) => ($b['type'] ?? null) === 'download_list')) {
            $this->info('Déjà converti : rien à faire.');

            return self::SUCCESS;
        }

        $html = collect($page->blocks)->firstWhere('type', 'rich_text')['data']['content'] ?? '';
        $items = $this->parse($html);

        if ($items === []) {
            $this->warn('Aucun document détecté : la page n\'est pas modifiée.');

            return self::FAILURE;
        }

        $page->blocks = [[
            'type' => 'download_list',
            'data' => [
                'title' => 'Centre de téléchargements',
                'intro' => 'Accédez à toutes nos ressources, documents et outils en téléchargement libre.',
                'items' => $items,
            ],
        ]];
        $page->save();

        $this->info(count($items).' documents convertis.');

        return self::SUCCESS;
    }

    /** @return list<array{title: string, text: ?string, url: string}> */
    private function parse(string $html): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>');

        $items = [];
        $current = null;
        $flush = function () use (&$items, &$current) {
            if ($current && $current['url']) {
                $items[] = $current;
            }
            $current = null;
        };

        foreach ($dom->getElementsByTagName('body')->item(0)->childNodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            if (in_array($node->nodeName, ['h4', 'h2'], true) && $this->text($node) !== '') {
                $flush();
                $current = ['title' => $this->text($node), 'text' => null, 'url' => null];

                continue;
            }

            if ($current === null || $node->nodeName !== 'p') {
                continue;
            }

            $link = $node->getElementsByTagName('a')->item(0);
            $text = $this->text($node);

            if ($link && $current['url'] === null && ($text === $this->text($link) || $current['title'] === 'Parcours Master')) {
                $current['url'] = $link->getAttribute('href');
                // « Parcours Master » : le paragraphe de présentation précède le lien.
            } elseif ($text !== '' && $current['text'] === null) {
                $current['text'] = $text;
            }

            if ($link && $current['url'] === null) {
                $current['url'] = $link->getAttribute('href');
            }
        }
        $flush();

        // Le dernier groupe (« Parcours Master ») devient un document explicite.
        return array_map(function (array $item) {
            if ($item['title'] === 'Parcours Master') {
                $item['title'] = 'Formulaire d\'admission — Master';
                $item['text'] = str($item['text'])->before('. ')->append('.')->toString();
            }

            return $item;
        }, $items);
    }

    private function text(DOMElement $node): string
    {
        return trim(preg_replace('/\s+/u', ' ', $node->textContent));
    }
}
