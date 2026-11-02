@use('App\Support\Locales')

{{-- Choix de la langue : même page dans les autres langues (adresse préfixée /en, /mg ; le français n'a pas de préfixe). --}}
<nav {{ $attributes->merge(['aria-label' => __('Langue du site')]) }}>
    <ul class="flex items-center gap-1">
        @foreach (Locales::all() as $code => $locale)
            <li>
                <a
                    href="{{ Locales::urlFor($code) }}"
                    lang="{{ $code }}"
                    hreflang="{{ $code }}"
                    title="{{ $locale['name'] }}"
                    @if ($code === Locales::current()) aria-current="true" @endif
                    @class([
                        'inline-flex min-w-8 items-center justify-center px-1.5 py-1 text-[0.6875rem] font-semibold tracking-wider transition-colors',
                        'text-sun-400 underline decoration-2 underline-offset-[5px]' => $code === Locales::current(),
                        'hover:text-sun-400' => $code !== Locales::current(),
                    ])
                >
                    <span aria-hidden="true">{{ $locale['short'] }}</span>
                    <span class="sr-only">{{ $locale['name'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
