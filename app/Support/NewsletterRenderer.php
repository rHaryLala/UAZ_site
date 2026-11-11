<?php

namespace App\Support;

use App\Models\MediaAsset;
use App\Models\NewsletterSubscriber;
use App\Models\NewsPost;
use App\Models\SiteSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

/**
 * Dessine une lettre d'information : le courrier HTML (tables et styles en ligne, pour les logiciels de messagerie) et sa version texte.
 * L'éditeur visuel affiche exactement ce que cette classe produit ($editing = true ajoute seulement des repères pour modifier sur place) :
 * ce qu'on voit sur le plan de travail est ce qui part.
 */
final class NewsletterRenderer
{
    /**
     * @param  array{subject?: string, preheader?: string, blocks?: array, theme?: array}  $state
     */
    public static function html(array $state, ?NewsletterSubscriber $subscriber = null, ?string $unsubscribeUrl = null, bool $editing = false, ?int $selected = null): string
    {
        $theme = NewsletterBlocks::theme($state['theme'] ?? null);
        $blocks = NewsletterBlocks::normalize($state['blocks'] ?? []);
        $unsubscribe = $unsubscribeUrl ?? ($subscriber ? URL::signedRoute('newsletter.unsubscribe', ['subscriber' => $subscriber]) : null);

        return view('newsletter.email', [
            'subject' => (string) ($state['subject'] ?? ''),
            'preheader' => (string) ($state['preheader'] ?? ''),
            'blocks' => $blocks,
            'theme' => $theme,
            'headingFont' => $theme['font'] === 'serif' ? "Georgia, 'Times New Roman', Times, serif" : "'Helvetica Neue', Helvetica, Arial, sans-serif",
            'media' => self::media($blocks),
            'site' => SiteSetting::current(),
            'unsubscribe' => $unsubscribe,
            'editing' => $editing,
            'selected' => $selected,
        ])->render();
    }

    /** Version texte brut : même contenu, sans mise en forme, pour les messageries qui n'affichent pas le HTML. */
    public static function text(array $state, ?string $unsubscribeUrl = null): string
    {
        $lines = [];
        $blocks = NewsletterBlocks::normalize($state['blocks'] ?? []);
        $strip = fn (string $html): string => trim(html_entity_decode(strip_tags(preg_replace(
            ['/<a [^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is', '/<li[^>]*>/i', '/<\/(p|h2|h3|ul|ol|blockquote)>/i', '/<br\s*\/?>/i'],
            ['$2 ($1)', '- ', "\n\n", "\n"],
            $html,
        )), ENT_QUOTES | ENT_HTML5));

        $lines[] = (string) ($state['subject'] ?? '');
        $lines[] = str_repeat('=', min(60, max(8, mb_strlen((string) ($state['subject'] ?? '')))));

        foreach ($blocks as $block) {
            $d = $block['data'];
            $lines[] = '';

            match ($block['type']) {
                'heading' => $lines[] = mb_strtoupper($d['text']),
                'text' => $lines[] = $strip($d['html']),
                'button' => $lines[] = $d['label'].($d['url'] !== '' ? ' : '.$d['url'] : ''),
                'quote' => $lines[] = '« '.$d['text'].' »'.($d['author'] !== '' ? ' — '.$d['author'] : ''),
                'feature' => $lines[] = trim($d['title']."\n".$strip($d['html']).($d['link'] !== '' ? "\n".$d['link'] : '')),
                'news' => $lines[] = trim($d['title']."\n".self::newsPosts((int) $d['count'])->map(fn (NewsPost $p) => '- '.$p->title.' : '.$p->url())->implode("\n")),
                'divider' => $lines[] = '--',
                default => null,
            };
        }

        $lines[] = '';
        $lines[] = '--';
        $lines[] = 'Université Adventiste Zurcher — PK 135, Route Nationale 7, Antsirabe, Madagascar';
        $lines[] = url('/');

        if ($unsubscribeUrl) {
            $lines[] = 'Se désinscrire : '.$unsubscribeUrl;
        }

        return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
    }

    /** Derniers articles publiés (bloc « Actualités du site »). @return Collection<int, NewsPost> */
    public static function newsPosts(int $count): Collection
    {
        return NewsPost::query()->published()->with('cover.media')->orderByDesc('published_at')->limit(max(1, min(6, $count)))->get();
    }

    /** Adresse absolue d'une image de la médiathèque (les courriers sont lus hors du site). */
    public static function imageUrl(?MediaAsset $asset, string $conversion = 'card'): ?string
    {
        return $asset && $asset->file() ? url($asset->url($conversion)) : null;
    }

    /**
     * Images citées par les blocs, chargées d'un coup.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return Collection<int, MediaAsset>
     */
    private static function media(array $blocks): Collection
    {
        $ids = collect($blocks)->pluck('data.media')->filter()->unique()->values();

        return MediaAsset::with('media')->whereIn('id', $ids)->get()->keyBy('id');
    }
}
