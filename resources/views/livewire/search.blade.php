<div>
    {{-- Formulaire GET classique : fonctionne sans JavaScript ; avec Livewire les résultats s'affichent en direct --}}
    <form method="get" action="{{ url('/recherche') }}" role="search" class="mx-auto flex max-w-2xl gap-3" wire:submit.prevent>
        <div class="relative flex-1">
            <label for="search-q" class="sr-only">{{ __('Rechercher sur le site') }}</label>
            <x-ico name="search" class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-ink-600" />
            <input id="search-q" type="search" name="q" wire:model.live.debounce.300ms="query" value="{{ $query }}" class="field-input pl-12" placeholder="{{ __('Formation, actualité, événement…') }}" autocomplete="off" autofocus>
        </div>
        <x-button type="submit" variant="navy">{{ __('Rechercher') }}</x-button>
    </form>

    <div class="mt-12" role="status" aria-live="polite">
        @if (mb_strlen($term) < 2)
            <p class="text-center text-ink-700">{{ __('Saisissez au moins 2 caractères pour lancer la recherche.') }}</p>
        @elseif ($results->isEmpty())
            <p class="text-center text-ink-700">{{ __('Aucun résultat pour «') }} <strong>{{ $term }}</strong> {{ __('». Essayez un autre mot, ou') }} <a href="{{ url('/contact') }}" class="link-arrow">{{ __('contactez-nous') }}</a>.</p>
        @else
            <p class="mb-10 text-sm text-ink-700">{{ __(':v résultat(s) pour «', ['v' => $results->flatten(1)->count()]) }} <strong>{{ $term }}</strong> »</p>
        @endif
    </div>

    @foreach ($results as $group => $items)
        <section class="mb-12" aria-labelledby="group-{{ \Illuminate\Support\Str::slug($group) }}">
            <h2 id="group-{{ \Illuminate\Support\Str::slug($group) }}" class="eyebrow mb-4">{{ \App\Support\Locales::tr($group) }}</h2>
            <ul class="divide-y divide-paper-300 rounded-card bg-paper-50 shadow-card">
                @foreach ($items as $item)
                    <li>
                        <a href="{{ $item['url'] }}" class="group block p-6 hover:bg-paper-200">
                            <span class="font-serif text-h4 text-navy-800 group-hover:underline">{{ $item['title'] }}</span>
                            @if ($item['meta'])<span class="ml-3 text-xs font-semibold tracking-wide text-gold-700 uppercase">{{ $item['meta'] }}</span>@endif
                            @if ($item['excerpt'])<span class="mt-1 block text-sm text-ink-700">{{ $item['excerpt'] }}</span>@endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach
</div>
