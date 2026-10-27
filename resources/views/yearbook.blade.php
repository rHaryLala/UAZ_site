<x-layouts.app :title="$book['title'].' — '.$book['subtitle']" description="Feuilletez en ligne le yearbook de l'Université Adventiste Zurcher : portraits de la promotion, vie du campus et souvenirs de l'année.">
    <header class="on-dark relative isolate overflow-hidden bg-navy-950 text-white">
        <x-triangle class="pointer-events-none absolute top-1/2 left-1/2 -z-10 w-[min(70rem,140vw)] -translate-x-1/2 -translate-y-[40%] text-gold-300/10" />
        <div class="container-site pt-10 pb-12 md:pb-16">
            <x-breadcrumbs :items="[['label' => 'Yearbook']]" dark class="mb-8" />
            <div class="flex flex-wrap items-end justify-between gap-x-10 gap-y-6">
                <div>
                    <p class="eyebrow mb-4 text-sun-400!" data-reveal>Livre numérique</p>
                    <h1 class="text-[clamp(2.5rem,1.4rem+4.4vw,5rem)] leading-[1.04] font-normal text-white!" data-reveal data-reveal-delay="0.1">{{ $book['title'] }} <span class="text-sun-400 italic">« {{ $book['subtitle'] }} »</span></h1>
                    <p class="mt-5 max-w-2xl text-lead text-white/80" data-reveal data-reveal-delay="0.2">Feuilletez le yearbook de l'Université Adventiste Zurcher : portraits, vie du campus et souvenirs de l'année.</p>
                </div>
                @if (count($books) > 1)
                    <nav aria-label="Autres éditions" class="flex flex-wrap gap-2">
                        @foreach ($books as $year => $item)
                            <a href="{{ url('/yearbook/'.$year) }}" @class(['inline-flex min-h-11 items-center border px-5 text-sm font-semibold transition', 'border-sun-400 bg-sun-400 text-night-900' => (string) $year === (string) $book['year'], 'border-white/30 hover:border-white' => (string) $year !== (string) $book['year']])>{{ $year }}</a>
                        @endforeach
                    </nav>
                @endif
            </div>
        </div>
    </header>

    <section data-yearbook class="yb on-dark relative isolate overflow-hidden bg-navy-950 pb-16 text-white md:pb-24">
        <div data-yb-stage class="yb-stage relative flex flex-col items-center px-3 py-10 sm:px-6 md:py-14">
            <div class="yb-book-wrap relative">
                <div data-yb-book class="yb-book" aria-label="{{ $book['title'] }} : livre à feuilleter" role="group">
                    @foreach ($pages as $page)
                        <div data-yb-page class="yb-page" data-density="{{ $loop->first || $loop->last ? 'hard' : 'soft' }}">
                            <img
                                @if ($loop->index < 2) src="{{ asset($page) }}" @else data-src="{{ asset($page) }}" @endif
                                alt="Page {{ $loop->iteration }} sur {{ $loop->count }} du {{ $book['title'] }}"
                                width="1100" height="1520" draggable="false" decoding="async"
                            >
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Commandes --}}
            <div class="yb-controls mt-8 flex w-full max-w-3xl flex-wrap items-center justify-center gap-x-4 gap-y-4 sm:mt-10">
                <button type="button" data-yb-prev class="yb-btn" aria-label="Page précédente"><x-ico name="arrow-left" class="size-5" /></button>
                <div class="min-w-[12rem] flex-1 sm:flex-none">
                    <p data-yb-label class="mb-2 text-center text-sm font-semibold tracking-wide" role="status" aria-live="polite">Page 1 / {{ $pages->count() }}</p>
                    <label class="sr-only" for="yb-slider">Aller à la page</label>
                    <input id="yb-slider" data-yb-slider type="range" min="1" max="{{ $pages->count() }}" value="1" class="yb-slider w-full sm:w-72">
                </div>
                <button type="button" data-yb-next class="yb-btn" aria-label="Page suivante"><x-ico name="arrow-right" class="size-5" /></button>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-center gap-3 text-sm">
                <button type="button" data-yb-thumbs-toggle aria-expanded="false" aria-controls="yb-thumbs" class="yb-chip"><x-ico name="grid" class="size-4" /> Miniatures</button>
                <button type="button" data-yb-fullscreen aria-pressed="false" class="yb-chip"><x-ico name="expand" class="size-4" /> Plein écran</button>
                <a href="{{ $book['pdf'] }}" target="_blank" rel="noopener noreferrer" class="yb-chip"><x-ico name="download" class="size-4" /> Télécharger le PDF <span class="sr-only">(nouvel onglet)</span></a>
            </div>
            <p class="mt-5 hidden text-xs text-white/50 md:block">Cliquez ou faites glisser un coin de page · flèches ← → du clavier</p>

            <noscript><p class="mt-8 max-w-lg text-center text-white/80">Le livre à feuilleter demande JavaScript. Vous pouvez <a href="{{ $book['pdf'] }}" class="underline">télécharger le PDF</a>.</p></noscript>
        </div>

        <div id="yb-thumbs" data-yb-thumbs hidden class="container-site max-w-6xl">
            <ul class="grid grid-cols-4 gap-3 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-11">
                @foreach ($pages as $page)
                    <li>
                        <button type="button" data-yb-goto="{{ $loop->index }}" class="yb-thumb group block w-full" aria-label="Aller à la page {{ $loop->iteration }}">
                            <img data-src="{{ asset(str_replace('/page-', '/thumbs/page-', $page)) }}" alt="" width="220" height="304" loading="lazy" class="block w-full">
                            <span class="mt-1 block text-center text-[0.7rem] text-white/60 group-hover:text-sun-400">{{ $loop->iteration }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
</x-layouts.app>
