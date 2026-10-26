<?php

namespace App\Services\Wordpress;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client en LECTURE SEULE de l'API REST WordPress (et des fichiers de médias).
 * Uniquement des requêtes GET, espacées d'au moins `delay_ms` (500 ms minimum), avec un User-Agent identifié,
 * et refus des chemins interdits par le robots.txt du site.
 */
class WordpressClient
{
    private const MIN_DELAY_MS = 500;

    private ?float $lastRequestAt = null;

    /** @var list<string>|null */
    private ?array $disallowed = null;

    public function __construct(
        private readonly string $baseUrl,
        private readonly int $delayMs = 600,
        private readonly string $userAgent = 'UAZ-importer/1.0 (lecture seule)',
        private readonly bool $enforceDelay = true,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            rtrim(config('uaz.wordpress.url'), '/'),
            (int) config('uaz.wordpress.delay_ms'),
            config('uaz.wordpress.user_agent'),
            // Les tests désactivent l'attente (aucune requête réelle n'est émise).
            ! app()->runningUnitTests(),
        );
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /** @return list<array<string, mixed>> */
    public function all(string $endpoint, array $query = []): array
    {
        $items = [];
        $page = 1;

        do {
            $response = $this->request('GET', "/wp-json/wp/v2/$endpoint", $query + ['per_page' => 100, 'page' => $page]);
            $items = [...$items, ...$response->json()];
            $pages = (int) ($response->header('X-WP-TotalPages') ?: 1);
        } while ($page++ < $pages);

        return $items;
    }

    /** Télécharge un fichier (média) et renvoie son contenu binaire. */
    public function download(string $url): string
    {
        return $this->request('GET', $url)->body();
    }

    private function request(string $method, string $pathOrUrl, array $query = [])
    {
        $url = str_starts_with($pathOrUrl, 'http') ? $pathOrUrl : $this->baseUrl.$pathOrUrl;
        $path = parse_url($url, PHP_URL_PATH) ?: '/';

        if ($this->isDisallowed($path)) {
            throw new RuntimeException("Chemin interdit par robots.txt : $path");
        }

        $this->wait();

        $response = $this->http()->send($method, $url, $query ? ['query' => $query] : []);

        if ($response->failed()) {
            throw new RuntimeException("HTTP {$response->status()} pour $url");
        }

        return $response;
    }

    private function http(): PendingRequest
    {
        return Http::withUserAgent($this->userAgent)->acceptJson()->timeout(90)->retry(2, 1500, throw: false);
    }

    private function wait(): void
    {
        if ($this->enforceDelay && $this->lastRequestAt !== null) {
            $elapsed = (microtime(true) - $this->lastRequestAt) * 1000;
            $needed = max($this->delayMs, self::MIN_DELAY_MS) - $elapsed;

            if ($needed > 0) {
                usleep((int) ($needed * 1000));
            }
        }

        $this->lastRequestAt = microtime(true);
    }

    private function isDisallowed(string $path): bool
    {
        if ($this->disallowed === null) {
            $this->disallowed = [];

            try {
                $this->wait();
                $robots = Http::withUserAgent($this->userAgent)->timeout(30)->get($this->baseUrl.'/robots.txt');
                $applies = false;

                foreach (preg_split('/\R/', $robots->successful() ? $robots->body() : '') as $line) {
                    $line = trim(preg_replace('/#.*/', '', $line));
                    if (preg_match('/^User-agent:\s*(.+)$/i', $line, $m)) {
                        $applies = trim($m[1]) === '*';
                    } elseif ($applies && preg_match('/^Disallow:\s*(\S+)/i', $line, $m)) {
                        $this->disallowed[] = $m[1];
                    }
                }
            } catch (\Throwable) {
                // robots.txt injoignable : on reste prudent mais on continue (aucun chemin explicitement interdit).
            }
        }

        foreach ($this->disallowed as $prefix) {
            if (str_starts_with($path, $prefix) && ! str_starts_with($path, '/wp-admin/admin-ajax.php')) {
                return true;
            }
        }

        return false;
    }
}
