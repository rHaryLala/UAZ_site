@php
    use Spatie\MediaLibrary\MediaCollections\Models\Media;

    $items = collect($data['items'] ?? []);
    $mediaIds = $items->map(fn ($i) => preg_match('#^/storage/(\d+)/#', (string) ($i['url'] ?? ''), $m) ? (int) $m[1] : null)->filter()->all();
    $stored = $mediaIds ? Media::whereIn('id', $mediaIds)->get()->keyBy('id') : collect();

    $types = [
        'pdf' => ['PDF', 'bg-royal-600'], 'doc' => ['DOC', 'bg-navy-800'], 'docx' => ['DOC', 'bg-navy-800'],
        'xls' => ['XLS', 'bg-emerald-800'], 'xlsx' => ['XLS', 'bg-emerald-800'], 'ppt' => ['PPT', 'bg-orange-800'], 'pptx' => ['PPT', 'bg-orange-800'],
        'zip' => ['ZIP', 'bg-ink-700'],
    ];

    // Normalise chaque document : adresse, type, taille et ouverture dans un nouvel onglet pour les liens externes.
    $docs = $items->map(function ($item) use ($assets, $stored, $types) {
        $asset = $assets->get($item['file_id'] ?? 0);
        $url = $asset?->url() ?: ($item['url'] ?? '');
        $url = preg_match('#^(/|https?://)#i', (string) $url) ? $url : null;
        $path = strtolower((string) parse_url((string) $url, PHP_URL_PATH));
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $isSheet = str_contains((string) $url, 'docs.google.com/spreadsheets');
        [$label, $color] = $isSheet ? ['XLS', 'bg-emerald-800'] : ($types[$ext] ?? [$url ? 'LIEN' : '—', 'bg-ink-600']);
        $bytes = $asset?->file()?->size;
        if (! $bytes && preg_match('#^/storage/(\d+)/#', (string) $url, $m)) {
            $bytes = $stored->get((int) $m[1])?->size;
        }
        $size = $bytes ? ($bytes >= 1048576 ? number_format($bytes / 1048576, 1, ',', ' ').' Mo' : max(1, round($bytes / 1024)).' Ko') : null;

        return [
            'title' => $item['title'] ?? '', 'text' => $item['text'] ?? null, 'url' => $url,
            'label' => $label, 'color' => $color, 'size' => $size, 'external' => $url && str_starts_with($url, 'http') && ! str_starts_with($url, url('/')),
        ];
    })->filter(fn ($d) => $d['url'])->values();
@endphp

@if ($docs->isNotEmpty())
    <section class="relative isolate overflow-hidden bg-paper-100 py-14 md:py-20">
        <x-madagascar class="pointer-events-none absolute top-24 -right-32 -z-10 h-[34rem] text-navy-800/[0.04]" />

        <div class="container-site max-w-5xl">
            @if (! empty($data['title']) || ! empty($data['intro']))
                <div class="mb-10 flex flex-wrap items-end justify-between gap-x-8 gap-y-4 border-b border-navy-800/15 pb-6" data-reveal>
                    <div class="max-w-2xl">
                        @if (! empty($data['title']))<h2 class="text-h2">{{ $data['title'] }}</h2>@endif
                        @if (! empty($data['intro']))<p class="mt-3 text-lead text-ink-700">{{ $data['intro'] }}</p>@endif
                    </div>
                    <p class="font-serif text-lg text-navy-800"><span class="text-3xl">{{ $docs->count() }}</span> <span class="text-ink-600">documents</span></p>
                </div>
            @endif

            <ul class="space-y-3">
                @foreach ($docs as $doc)
                    <li data-reveal data-reveal-delay="{{ min($loop->index, 6) * 0.04 }}">
                        <article class="group relative flex flex-wrap items-center gap-x-6 gap-y-4 border border-navy-800/10 bg-white p-4 pr-5 shadow-card transition duration-300 hover:-translate-y-0.5 hover:border-sun-400 hover:shadow-lift focus-within:border-sun-400 sm:flex-nowrap sm:p-5">
                            <span class="absolute inset-y-0 left-0 w-1 origin-top scale-y-0 bg-sun-400 transition-transform duration-300 group-hover:scale-y-100 group-focus-within:scale-y-100" aria-hidden="true"></span>

                            <span class="flex size-14 shrink-0 flex-col items-center justify-center text-white {{ $doc['color'] }}" aria-hidden="true">
                                <x-ico name="download" class="size-4 text-sun-400" />
                                <span class="mt-0.5 text-[0.65rem] font-bold tracking-wider">{{ $doc['label'] }}</span>
                            </span>

                            <div class="min-w-0 flex-1">
                                <h3 class="font-serif text-lg leading-snug text-navy-900 md:text-xl">
                                    <a href="{{ $doc['url'] }}" class="stretched-link focus-visible:outline-offset-8" @if ($doc['external']) target="_blank" rel="noopener noreferrer" @else download @endif>{{ $doc['title'] }}<span class="sr-only"> — télécharger ({{ $doc['label'] }}@if ($doc['size']), {{ $doc['size'] }}@endif)@if ($doc['external']), nouvel onglet @endif</span></a>
                                </h3>
                                @if ($doc['text'])<p class="mt-1 line-clamp-2 text-sm text-ink-700">{{ $doc['text'] }}</p>@endif
                            </div>

                            <div class="flex shrink-0 items-center gap-4 sm:ml-auto">
                                @if ($doc['size'])<span class="hidden text-xs font-medium text-ink-600 md:inline">{{ $doc['size'] }}</span>@endif
                                <span class="btn btn-royal btn-square btn-sm pointer-events-none" aria-hidden="true">Télécharger <x-ico name="download" /></span>
                            </div>
                        </article>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
