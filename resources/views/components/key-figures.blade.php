@props(['figures' => [], 'tone' => 'light'])

{{--
    Chiffres clés. La valeur finale est déjà dans le HTML (SEO, lecteurs d'écran, sans JS) ;
    le compteur animé (animations/counters.js) la fait seulement « monter » depuis 0 au défilement.
--}}
@php($dark = $tone === 'dark')

@if (count($figures))
    <dl @class(['grid gap-x-8 gap-y-10 sm:grid-cols-2 lg:grid-cols-4', 'on-dark' => $dark]) data-key-figures>
        @foreach ($figures as $figure)
            @php($value = (int) ($figure['value'] ?? 0))
            <div class="border-t-2 border-gold-400 pt-5" data-reveal data-reveal-delay="{{ $loop->index * 0.08 }}">
                <dd @class(['order-first font-serif text-figure leading-none', 'text-white' => $dark, 'text-navy-800' => ! $dark])>
                    @php($final = ($value >= 10000 ? number_format($value, 0, ',', "\u{202F}") : $value).($figure['suffix'] ?? ''))
                    <span aria-hidden="true"><span data-counter data-value="{{ $value }}">{{ $value >= 10000 ? number_format($value, 0, ',', "\u{202F}") : $value }}</span><span @class(['text-gold-300' => $dark, 'text-gold-600' => ! $dark])>{{ $figure['suffix'] ?? '' }}</span></span>
                    <span class="sr-only">{{ $final }}</span>
                </dd>
                <dt @class(['mt-3 text-sm font-medium', 'text-ink-700' => ! $dark, 'text-navy-100' => $dark])>{{ $figure['label'] ?? '' }}</dt>
            </div>
        @endforeach
    </dl>
@endif
