<?php

namespace App\Support;

use App\Models\MediaAsset;
use App\Models\NewsletterSubscriber;
use App\Models\NewsPost;
use App\Models\SiteSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Dessine une newsletter : le courrier HTML (tables et styles en ligne, pour les logiciels de messagerie) et sa version texte.
 * L'éditeur visuel affiche exactement ce que cette classe produit ($editing = true ajoute seulement des repères pour modifier sur place) :
 * ce qu'on voit sur le plan de travail est ce qui part.
 */
final class NewsletterRenderer
{
    /** Largeur de la newsletter et de ses marges (px) : les colonnes en tirent leurs largeurs exactes (les messageries n'aiment pas le « calc »). */
    public const WIDTH = 640;

    public const SIDE = 32;

    /**
     * @param  array{subject?: string, preheader?: string, blocks?: array, theme?: array}  $state
     */
    public static function html(array $state, ?NewsletterSubscriber $subscriber = null, ?string $unsubscribeUrl = null, bool $editing = false): string
    {
        $theme = NewsletterBlocks::theme($state['theme'] ?? null);
        $blocks = NewsletterBlocks::normalize($state['blocks'] ?? []);
        $ctx = self::context($blocks, $theme, $editing);
        $unsubscribe = $unsubscribeUrl ?? ($subscriber ? URL::signedRoute('newsletter.unsubscribe', ['subscriber' => $subscriber]) : null);

        return view('newsletter.email', [
            'subject' => (string) ($state['subject'] ?? ''),
            'preheader' => (string) ($state['preheader'] ?? ''),
            'header' => self::header($theme, $editing),
            'body' => self::nodes($blocks, 0, self::WIDTH - 2 * self::SIDE, $ctx),
            'hasBlocks' => $blocks !== [],
            'theme' => $theme,
            'headingFont' => $ctx['headingFont'],
            'site' => SiteSetting::current(),
            'unsubscribe' => $unsubscribe,
            'editing' => $editing,
        ])->render();
    }

    /**
     * Les lignes de l'en-tête (bandeau, et filet doré s'il est demandé) : le plan de travail les remplace seules quand on règle l'en-tête.
     * Elles comprennent le petit blanc avant le contenu (avec un filet, ou sans en-tête).
     *
     * @param  array<string, string|int>  $theme
     */
    public static function header(array $theme, bool $editing = false): string
    {
        return view('newsletter.header', [
            'theme' => $theme,
            'editing' => $editing,
            'headingFont' => $theme['font'] === 'serif' ? "Georgia, 'Times New Roman', Times, serif" : "'Helvetica Neue', Helvetica, Arial, sans-serif",
            'dark' => self::isDark($theme['header_color']),
        ])->render();
    }

    /**
     * Les lignes de la newsletter pour quelques blocs seulement (mise à jour du plan de travail : on ne redessine que ce qui a changé).
     *
     * @param  list<array<string, mixed>>  $blocks  blocs de premier niveau, déjà valides
     * @param  array<string, string>  $theme
     * @return list<string>
     */
    public static function rows(array $blocks, array $theme): array
    {
        $ctx = self::context($blocks, $theme, true);

        return array_map(fn (array $block) => self::nodes([$block], 0, self::WIDTH - 2 * self::SIDE, $ctx), $blocks);
    }

    /**
     * Ce que partagent tous les blocs au dessin : thème, police des titres, images, mode d'édition.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @param  array<string, string>  $theme
     * @return array{theme: array<string, string>, headingFont: string, media: Collection<int, MediaAsset>, editing: bool}
     */
    public static function context(array $blocks, array $theme, bool $editing): array
    {
        return [
            'theme' => $theme,
            'headingFont' => $theme['font'] === 'serif' ? "Georgia, 'Times New Roman', Times, serif" : "'Helvetica Neue', Helvetica, Arial, sans-serif",
            'media' => self::media($blocks),
            'editing' => $editing,
        ];
    }

    /**
     * Dessine une liste de blocs au niveau $depth, dans une place de $width px (récursif : les conteneurs rappellent cette méthode).
     *
     * @param  list<array<string, mixed>>  $list
     * @param  array<string, mixed>  $ctx
     */
    public static function nodes(array $list, int $depth, int $width, array $ctx): string
    {
        $html = '';

        foreach ($list as $block) {
            $html .= view('newsletter.node', $ctx + ['ctx' => $ctx, 'block' => $block, 'depth' => $depth, 'width' => $width])->render();
        }

        return $html;
    }

    /** Un fond sombre ? Le texte des blocs posés dessus passe alors en clair (voir le bloc « section »). */
    public static function isDark(string $hex): bool
    {
        if (! preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $hex, $m)) {
            return false;
        }

        return (0.299 * hexdec($m[1]) + 0.587 * hexdec($m[2]) + 0.114 * hexdec($m[3])) < 140;
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

        foreach (NewsletterBlocks::flatten($blocks) as $block) {
            $d = $block['data'];
            $lines[] = '';

            match ($block['type']) {
                'heading' => $lines[] = mb_strtoupper($d['text']),
                'text' => $lines[] = $strip($d['html']),
                'button' => $lines[] = $d['label'].($d['url'] !== '' ? ' : '.$d['url'] : ''),
                'quote' => $lines[] = '« '.$d['text'].' »'.($d['author'] !== '' ? ' — '.$d['author'] : ''),
                'hero' => $lines[] = trim(implode("\n", array_filter([$d['eyebrow'], mb_strtoupper($d['title']), $d['text'], $d['button_label'].($d['button_url'] !== '' ? ' : '.$d['button_url'] : '')]))),
                'feature' => $lines[] = trim($d['title']."\n".$strip($d['html']).($d['link'] !== '' ? "\n".$d['link'] : '')),
                'news' => $lines[] = trim($d['title']."\n".self::newsPosts((int) $d['count'])->map(fn (NewsPost $p) => '- '.$p->title.' : '.$p->url())->implode("\n")),
                'divider' => $lines[] = '--',
                default => $lines = array_slice($lines, 0, -1), // conteneurs et espaces : rien à dire (le contenu des conteneurs suit)
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
    public static function imageUrl(?MediaAsset $asset, string $conversion = 'mail'): ?string
    {
        $file = $asset?->file();

        if (! $file) {
            return null;
        }

        // Le JPEG des newsletters s'il existe (toutes les messageries l'affichent), sinon la version WebP « card », sinon l'original.
        foreach ([$conversion, 'card'] as $name) {
            if ($file->hasGeneratedConversion($name)) {
                return url($file->getUrl($name));
            }
        }

        return url($file->getUrl());
    }

    /**
     * Une photo recadrée À LA TAILLE EXACTE (« cover » : elle remplit le cadre, centrée), en JPEG, au double de la taille d'affichage pour les écrans nets.
     * Les messageries ne savent pas recadrer (« object-fit » est mal pris en charge) : les mosaïques et les photos en fond reçoivent donc des fichiers déjà
     * découpés. Le fichier est créé au premier besoin dans storage/app/public/newsletter-tiles puis réutilisé ; en cas d'échec, la photo entière sert de repli.
     *
     * @param  float  $darken  0 à 1 : assombrit la photo (texte clair posé directement dessus)
     */
    public static function crop(?MediaAsset $asset, int $width, int $height, float $darken = 0): ?string
    {
        $file = $asset?->file();

        if (! $file || $width < 8 || $height < 8) {
            return self::imageUrl($asset);
        }

        $source = $file->getPath();

        if (! is_file($source)) {
            return self::imageUrl($asset);
        }

        $name = sprintf('%d-%d-%dx%d%s-%s.jpg', $asset->id, $file->id, $width, $height, $darken > 0 ? '-d'.(int) round($darken * 100) : '', substr(md5(filemtime($source).filesize($source)), 0, 6));
        $disk = Storage::disk('public');
        $target = 'newsletter-tiles/'.$name;

        if (! $disk->exists($target)) {
            try {
                self::writeCrop($source, $disk->path($target), $width * 2, $height * 2, $darken);
            } catch (\Throwable $e) {
                report($e);

                return self::imageUrl($asset);
            }
        }

        return url('/storage/'.$target);
    }

    /** Découpe « cover » avec GD : l'image est réduite pour couvrir le cadre, puis centrée. */
    private static function writeCrop(string $source, string $destination, int $width, int $height, float $darken): void
    {
        $image = @imagecreatefromstring((string) file_get_contents($source));

        if (! $image) {
            throw new \RuntimeException('Image illisible : '.basename($source));
        }

        [$sw, $sh] = [imagesx($image), imagesy($image)];
        $scale = max($width / $sw, $height / $sh);
        [$cw, $ch] = [(int) ceil($width / $scale), (int) ceil($height / $scale)];
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255)); // fond blanc sous les PNG transparents
        imagecopyresampled($canvas, $image, 0, 0, (int) max(0, floor(($sw - $cw) / 2)), (int) max(0, floor(($sh - $ch) / 2)), $width, $height, min($cw, $sw), min($ch, $sh));

        if ($darken > 0) {
            imagealphablending($canvas, true);
            imagefilledrectangle($canvas, 0, 0, $width, $height, imagecolorallocatealpha($canvas, 8, 12, 36, (int) round(127 * (1 - min(0.85, $darken)))));
        }

        $directory = dirname($destination);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $temporary = $destination.'.'.bin2hex(random_bytes(4)).'.tmp';
        imagejpeg($canvas, $temporary, 82);
        rename($temporary, $destination); // d'un coup : une messagerie ne lit jamais un fichier à moitié écrit
    }

    /** « #131a3f » + 88 % → « rgba(19,26,63,0.88) » (le panneau translucide des messageries qui le permettent). */
    public static function rgba(string $hex, int $percent): string
    {
        if (! preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $hex, $m)) {
            return $hex;
        }

        return sprintf('rgba(%d,%d,%d,%s)', hexdec($m[1]), hexdec($m[2]), hexdec($m[3]), rtrim(rtrim(number_format(max(0, min(100, $percent)) / 100, 2, '.', ''), '0'), '.'));
    }

    /**
     * Images citées par les blocs, chargées d'un coup.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return Collection<int, MediaAsset>
     */
    private static function media(array $blocks): Collection
    {
        return MediaAsset::with('media')->whereIn('id', NewsletterBlocks::mediaIds($blocks))->get()->keyBy('id');
    }
}
