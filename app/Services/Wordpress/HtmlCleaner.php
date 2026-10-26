<?php

namespace App\Services\Wordpress;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Nettoie le HTML rendu par WordPress/Divi : supprime shortcodes, commentaires Gutenberg, scripts, styles en ligne,
 * classes et conteneurs `et_pb_*`, réécrit les liens internes et remplace les images par des références à la
 * médiathèque (`<figure data-media="ID">`, rendues en images responsives par App\Support\Html).
 */
class HtmlCleaner
{
    /** Balises conservées ; tout le reste est « déroulé » (le contenu reste, l'enveloppe disparaît). */
    private const KEEP = [
        'p', 'br', 'strong', 'em', 'u', 'a', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'figure', 'figcaption', 'hr', 'sup', 'sub',
    ];

    private const REMOVE = ['script', 'style', 'noscript', 'form', 'button', 'input', 'select', 'textarea', 'svg', 'canvas', 'link', 'meta', 'object', 'embed'];

    private const BLOCK = ['p', 'ul', 'ol', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'table', 'figure', 'hr'];

    private const RENAME = ['b' => 'strong', 'i' => 'em', 'h1' => 'h2'];

    /** @var list<string> */
    private array $warnings = [];

    /** @var list<string> */
    private array $unresolvedLinks = [];

    /** @var list<string> */
    private array $unresolvedImages = [];

    public function __construct(private readonly string $siteHost = 'zurcher.edu.mg') {}

    /**
     * @param  (callable(string): ?int)|null  $imageResolver  URL d'image → identifiant de MediaAsset (ou null)
     * @param  (callable(string): ?string)|null  $linkResolver  URL interne → nouvelle adresse (ou null si inconnue)
     */
    public function clean(string $html, ?callable $imageResolver = null, ?callable $linkResolver = null): string
    {
        $this->warnings = $this->unresolvedLinks = $this->unresolvedImages = [];

        // Commentaires (Gutenberg « <!-- wp:… -->») et shortcodes (Divi, WPBakery, galeries…).
        $html = preg_replace('/<!--.*?-->/s', '', $html);
        $html = preg_replace('/\[\/?(?:et_pb_[a-z_]+|vc_[a-z_]+|caption|gallery|embed|audio|video)[^\]]*\]/i', '', $html);

        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?><div id="uaz-root">'.$html.'</div>', LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $root = $dom->getElementById('uaz-root');

        if (! $root) {
            return '';
        }

        $this->removeUnwanted($dom, $root);
        $this->handleIframes($dom, $root);
        $this->convertDocumentThumbnails($dom, $root);
        $this->handleImages($dom, $root, $imageResolver);
        $this->renameTags($dom, $root);
        $this->unwrapOthers($root);
        $this->cleanAttributes($root);
        $this->rewriteLinks($root, $linkResolver);
        $this->wrapLooseInline($dom, $root);
        $this->unwrapArtifactBold($root);
        $this->removeEmpty($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        $out = str_replace("\u{00A0}", ' ', $out);
        $out = preg_replace('/\s*\n\s*/', "\n", $out);
        $out = preg_replace('/>\s+</', '><', $out);

        return trim($out);
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /** @return list<string> */
    public function unresolvedLinks(): array
    {
        return array_values(array_unique($this->unresolvedLinks));
    }

    /** @return list<string> */
    public function unresolvedImages(): array
    {
        return array_values(array_unique($this->unresolvedImages));
    }

    /** @return list<DOMElement> */
    private function elements(DOMNode $root, ?string $tag = null): array
    {
        $nodes = [];
        foreach ($root->getElementsByTagName($tag ?? '*') as $node) {
            $nodes[] = $node;
        }

        return $nodes;
    }

    private function removeUnwanted(DOMDocument $dom, DOMElement $root): void
    {
        foreach (self::REMOVE as $tag) {
            foreach ($this->elements($root, $tag) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }
    }

    private function handleIframes(DOMDocument $dom, DOMElement $root): void
    {
        foreach ($this->elements($root, 'iframe') as $iframe) {
            $src = $iframe->getAttribute('src');

            if (preg_match('~youtube(?:-nocookie)?\.com/embed/([\w-]{6,})~', $src, $m)) {
                $p = $dom->createElement('p');
                $a = $dom->createElement('a', 'Voir la vidéo sur YouTube');
                $a->setAttribute('href', 'https://www.youtube.com/watch?v='.$m[1]);
                $p->appendChild($a);
                $iframe->parentNode->replaceChild($p, $iframe);
                $this->warnings[] = 'Vidéo YouTube intégrée remplacée par un lien : '.$m[1];
            } else {
                $this->warnings[] = 'Contenu intégré (iframe) supprimé : '.$src;
                $iframe->parentNode->removeChild($iframe);
            }
        }
    }

    /**
     * Une image cliquable qui mène à un document (PDF, Word…) sans aucun texte, typique de Divi, devient un lien
     * texte « Télécharger … » : accessible, indexable et utilisable sans l'image.
     */
    private function convertDocumentThumbnails(DOMDocument $dom, DOMElement $root): void
    {
        foreach ($this->elements($root, 'a') as $a) {
            $href = $a->getAttribute('href');

            if (! preg_match('~\.(pdf|docx?|xlsx?|pptx?)(\?.*)?$~i', $href)
                || trim($a->textContent) !== ''
                || $a->getElementsByTagName('img')->length !== 1) {
                continue;
            }

            $img = $a->getElementsByTagName('img')->item(0);
            $label = trim($img->getAttribute('alt')) ?: ucfirst(trim(preg_replace('/[-_]+/', ' ', urldecode(pathinfo(parse_url($href, PHP_URL_PATH), PATHINFO_FILENAME)))));
            $ext = strtoupper(pathinfo(parse_url($href, PHP_URL_PATH), PATHINFO_EXTENSION));

            $link = $dom->createElement('a', "Télécharger : $label ($ext)");
            $link->setAttribute('href', $href);
            $p = $dom->createElement('p');
            $p->appendChild($link);

            $holder = $a->parentNode;
            $holder instanceof DOMElement && $holder->tagName === 'p' && trim($holder->textContent) === '' && $holder->childNodes->length === 1
                ? $holder->parentNode->replaceChild($p, $holder)
                : $a->parentNode->replaceChild($p, $a);
        }
    }

    /** Images → <figure data-media="ID"> (ou <img> d'origine si l'image n'a pas pu être importée). */
    private function handleImages(DOMDocument $dom, DOMElement $root, ?callable $resolver): void
    {
        foreach ($this->elements($root, 'img') as $img) {
            $url = $img->getAttribute('src') ?: $img->getAttribute('data-src') ?: $img->getAttribute('data-lazy-src');
            $id = ($url && $resolver) ? $resolver($url) : null;

            // Cible à remplacer : le lien enveloppant (vers le fichier) ou le <figure> existant, sinon l'image seule.
            $target = $img;
            $parent = $img->parentNode;

            if ($parent instanceof DOMElement && $parent->tagName === 'a' && $this->hasOnlyChild($parent, $img)
                && preg_match('~\.(jpe?g|png|webp|gif|avif)(\?.*)?$~i', $parent->getAttribute('href'))) {
                $target = $parent;
            }

            if (! $id) {
                if ($url) {
                    $this->unresolvedImages[] = $url;
                }
                // Image non importée : on conserve une <img> simple (sans srcset ni classes) plutôt que de perdre l'information.
                foreach (['srcset', 'sizes', 'class', 'style', 'loading', 'decoding', 'data-src', 'data-lazy-src', 'title'] as $attr) {
                    $img->removeAttribute($attr);
                }

                continue;
            }

            $figure = $dom->createElement('figure');
            $figure->setAttribute('data-media', (string) $id);

            $existing = $target->parentNode;
            if ($existing instanceof DOMElement && $existing->tagName === 'figure') {
                // <figure><img><figcaption>… : on garde la légende, on remplace seulement l'image.
                $existing->setAttribute('data-media', (string) $id);
                $existing->removeChild($target);

                continue;
            }

            $target->parentNode->replaceChild($figure, $target);

            // Un paragraphe qui ne contenait que l'image devient la figure.
            $holder = $figure->parentNode;
            if ($holder instanceof DOMElement && $holder->tagName === 'p' && trim($holder->textContent) === '' && $holder->childNodes->length === 1) {
                $holder->parentNode->replaceChild($figure, $holder);
            }
        }
    }

    private function hasOnlyChild(DOMElement $parent, DOMNode $child): bool
    {
        $meaningful = 0;
        foreach ($parent->childNodes as $node) {
            if ($node->nodeType === XML_TEXT_NODE && trim($node->textContent) === '') {
                continue;
            }
            $meaningful++;
        }

        return $meaningful === 1 && $child->parentNode === $parent;
    }

    private function renameTags(DOMDocument $dom, DOMElement $root): void
    {
        foreach (self::RENAME as $from => $to) {
            foreach ($this->elements($root, $from) as $node) {
                $new = $dom->createElement($to);
                while ($node->firstChild) {
                    $new->appendChild($node->firstChild);
                }
                $node->parentNode->replaceChild($new, $node);
            }
        }
    }

    /** Déroule tout ce qui n'est pas dans la liste blanche (div, section, span, classes Divi…), du plus profond au plus haut. */
    private function unwrapOthers(DOMElement $root): void
    {
        foreach (array_reverse($this->elements($root)) as $node) {
            if ($node === $root || in_array($node->tagName, [...self::KEEP, 'img'], true)) {
                continue;
            }

            while ($node->firstChild) {
                $node->parentNode->insertBefore($node->firstChild, $node);
            }
            $node->parentNode->removeChild($node);
        }
    }

    private function cleanAttributes(DOMElement $root): void
    {
        $allowed = [
            'a' => ['href'],
            'img' => ['src', 'alt', 'width', 'height'],
            'td' => ['colspan', 'rowspan'],
            'th' => ['colspan', 'rowspan'],
            'figure' => ['data-media'],
        ];

        foreach ($this->elements($root) as $node) {
            $keep = $allowed[$node->tagName] ?? [];
            foreach (iterator_to_array($node->attributes) as $attr) {
                if (! in_array($attr->name, $keep, true)) {
                    $node->removeAttribute($attr->name);
                }
            }
        }
    }

    private function rewriteLinks(DOMElement $root, ?callable $resolver): void
    {
        foreach ($this->elements($root, 'a') as $a) {
            $href = trim($a->getAttribute('href'));

            if ($href === '' || str_starts_with($href, '#') || preg_match('~^(mailto|tel):~i', $href)) {
                continue;
            }

            if (preg_match('~^javascript:~i', $href)) {
                $a->removeAttribute('href');

                continue;
            }

            if (! $this->isInternal($href)) {
                continue;
            }

            $fragment = parse_url($href, PHP_URL_FRAGMENT);
            $resolved = $resolver ? $resolver($href) : null;

            if ($resolved) {
                $a->setAttribute('href', $resolved.($fragment ? '#'.$fragment : ''));
            } elseif (parse_url($href, PHP_URL_PATH) !== '/' && parse_url($href, PHP_URL_PATH) !== null) {
                $this->unresolvedLinks[] = $href;
            }
        }
    }

    private function isInternal(string $href): bool
    {
        $host = parse_url($href, PHP_URL_HOST);

        return $host === null ? str_starts_with($href, '/') : in_array(strtolower($host), [$this->siteHost, 'www.'.$this->siteHost], true);
    }

    /** Texte et éléments en ligne posés directement dans la racine (après déroulage) : regroupés dans des <p>. */
    private function wrapLooseInline(DOMDocument $dom, DOMElement $root): void
    {
        $run = [];
        $flush = function () use (&$run, $dom, $root): void {
            if ($run === []) {
                return;
            }
            $text = trim(implode('', array_map(fn ($n) => $n->textContent, $run)));
            $hasMedia = collect($run)->contains(fn ($n) => $n instanceof DOMElement && ($n->tagName === 'img' || $n->getElementsByTagName('img')->length > 0));

            if ($text !== '' || $hasMedia) {
                $p = $dom->createElement('p');
                $root->insertBefore($p, $run[0]);
                foreach ($run as $node) {
                    $p->appendChild($node);
                }
            } else {
                foreach ($run as $node) {
                    $root->removeChild($node);
                }
            }
            $run = [];
        };

        foreach (iterator_to_array($root->childNodes) as $node) {
            $isBlock = $node instanceof DOMElement && in_array($node->tagName, self::BLOCK, true);

            if ($isBlock) {
                $flush();
            } else {
                $run[] = $node;
            }
        }
        $flush();
    }

    /**
     * Artefact d'éditeur : de longs paragraphes entièrement en gras. On retire le gras quand il couvre tout
     * le paragraphe et que celui-ci dépasse 140 caractères (le gras des titres courts est conservé).
     */
    private function unwrapArtifactBold(DOMElement $root): void
    {
        foreach ($this->elements($root, 'p') as $p) {
            $children = array_values(array_filter(iterator_to_array($p->childNodes), fn ($n) => ! ($n->nodeType === XML_TEXT_NODE && trim($n->textContent) === '')));

            if (count($children) === 1 && $children[0] instanceof DOMElement && $children[0]->tagName === 'strong'
                && mb_strlen(trim($p->textContent)) > 140) {
                $strong = $children[0];
                while ($strong->firstChild) {
                    $p->insertBefore($strong->firstChild, $strong);
                }
                $p->removeChild($strong);
            }
        }
    }

    /** Supprime les éléments vides (paragraphes d'espacement, liens sans texte…), jusqu'à stabilisation. */
    private function removeEmpty(DOMElement $root): void
    {
        $xpath = new DOMXPath($root->ownerDocument);

        do {
            $removed = 0;
            foreach ($this->elements($root) as $node) {
                if (in_array($node->tagName, ['br', 'hr', 'img', 'figure', 'td', 'th', 'tr', 'table', 'thead', 'tbody', 'tfoot'], true)) {
                    continue;
                }
                // Un paragraphe ne contenant qu'une image est utile ; un paragraphe ne contenant que des <br> ne l'est pas.
                $hasContent = trim(str_replace("\u{00A0}", ' ', $node->textContent)) !== ''
                    || $xpath->query('.//img|.//figure', $node)->length > 0
                    || ($node->tagName !== 'p' && $xpath->query('.//br', $node)->length > 0);

                if (! $hasContent && $node !== $root) {
                    $node->parentNode->removeChild($node);
                    $removed++;
                }
            }
        } while ($removed > 0);

        // Lignes <br> en tête/fin de paragraphe.
        foreach ($this->elements($root, 'br') as $br) {
            if (! $br->previousSibling && ! $br->nextSibling) {
                $br->parentNode->removeChild($br);
            }
        }
    }
}
