{{-- Fragment de résultats pour la fenêtre de recherche (inséré par components/search-modal.js). Liens : [data-search-result]. --}}
@if (mb_strlen($term) < \App\Services\SiteSearch::MIN_LENGTH)
    <p class="px-6 py-10 text-center text-sm text-ink-700">Saisissez au moins 2 caractères pour lancer la recherche.</p>
@elseif ($results->isEmpty())
    <p class="px-6 py-10 text-center text-sm text-ink-700">Aucun résultat pour « <strong class="text-ink-900">{{ $term }}</strong> ». Essayez un autre mot, ou <a href="{{ url('/contact') }}" class="link-arrow" data-search-result>contactez-nous</a>.</p>
@else
    <p class="sr-only" role="status">{{ $results->flatten(1)->count() }} résultat(s) pour « {{ $term }} »</p>
    @foreach ($results as $group => $items)
        <section class="px-3 pt-4" aria-label="{{ $group }}">
            <h3 class="eyebrow mb-1 px-3 font-sans! text-[0.6875rem]! text-ink-600">{{ $group }}</h3>
            <ul>
                @foreach ($items as $item)
                    <li>
                        <a href="{{ $item['url'] }}" data-search-result class="group flex items-start gap-3 rounded-lg px-3 py-2.5 hover:bg-mist-100 focus-visible:bg-mist-100">
                            <x-ico name="arrow-up-right" class="mt-1 size-4 text-royal-600 opacity-60 group-hover:opacity-100" />
                            <span class="min-w-0">
                                <span class="block font-serif text-[0.95rem] leading-snug text-navy-900 group-hover:text-royal-600">{{ $item['title'] }}</span>
                                @if ($item['meta'] || $item['excerpt'])
                                    <span class="mt-0.5 block truncate text-xs text-ink-600">@if ($item['meta'])<span class="font-semibold text-gold-700">{{ $item['meta'] }}</span>@if ($item['excerpt']) · @endif @endif{{ $item['excerpt'] }}</span>
                                @endif
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach
    <p class="mt-2 border-t border-paper-300 px-6 py-3 text-sm">
        <a href="{{ url('/recherche') }}?q={{ urlencode($term) }}" data-search-result class="link-arrow">Voir tous les résultats</a>
    </p>
@endif
