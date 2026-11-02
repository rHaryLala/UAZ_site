@props(['dropdown' => false])

@use('App\Support\Locales')

{{-- Choix de la langue : même page dans les autres langues (adresse préfixée /en, /mg ; le français n'a pas de préfixe).
     « dropdown » : la langue courante seule, les autres dans une liste (survol ou clavier, comme « Se connecter »). --}}
@if ($dropdown)
    <div {{ $attributes->merge(['class' => 'group relative']) }}>
        <button type="button" aria-haspopup="true" class="inline-flex cursor-default items-center gap-1 py-2 text-[0.6875rem] font-semibold tracking-wider transition-colors group-hover:text-sun-400 group-focus-within:text-sun-400">
            <span class="sr-only">{{ __('Langue du site') }} :</span>
            <span lang="{{ Locales::current() }}"><span aria-hidden="true">{{ Locales::all()[Locales::current()]['short'] }}</span><span class="sr-only">{{ Locales::all()[Locales::current()]['name'] }}</span></span>
            <x-ico name="chevron-down" class="size-3 transition-transform group-hover:rotate-180 group-focus-within:rotate-180" />
        </button>
        <ul class="invisible absolute top-full right-0 z-[70] w-40 translate-y-1 bg-white p-2 text-ink-800 opacity-0 shadow-lift transition duration-200 group-focus-within:visible group-focus-within:translate-y-0 group-focus-within:opacity-100 group-hover:visible group-hover:translate-y-0 group-hover:opacity-100">
            @foreach (Locales::all() as $code => $locale)
                <li>
                    <a
                        href="{{ Locales::urlFor($code) }}"
                        lang="{{ $code }}"
                        hreflang="{{ $code }}"
                        @if ($code === Locales::current()) aria-current="true" @endif
                        @class([
                            'flex items-center justify-between px-4 py-2.5 text-sm hover:bg-mist-100 hover:text-royal-600!',
                            'font-semibold text-royal-600' => $code === Locales::current(),
                            'font-medium text-ink-800' => $code !== Locales::current(),
                        ])
                    >
                        {{ $locale['name'] }}
                        <span class="text-[0.6875rem] tracking-wider text-ink-600" aria-hidden="true">{{ $locale['short'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@else
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
@endif
