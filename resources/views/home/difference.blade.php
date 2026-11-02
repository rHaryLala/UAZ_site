@php($figures = $site->key_figures ?? [])

{{-- « La différence UAZ » : la phrase s'allume mot à mot sur un écran arrêté, puis les chiffres clés se comptent (animations : resources/js/animations/home.js). --}}
<section id="difference" class="home-diff" data-home="diff" aria-labelledby="difference-title">
    <div class="home-diff__stage" data-home-stage>
        <div class="container-site">
            <p class="eyebrow mb-8" data-reveal>{{ __('home.difference.eyebrow') }}</p>
            <h2 id="difference-title" class="home-diff__title" data-home-light>{{ __('home.difference.title') }}</h2>
            <div class="home-diff__more" data-home-after>
                <p class="text-lead text-ink-700">{{ __('home.difference.text') }}</p>
                <a href="{{ url('/a-propos') }}" class="link-arrow mt-6">{{ __('home.difference.link') }} <x-ico name="arrow-right" class="size-4" /></a>
            </div>
        </div>
    </div>

    @if (count($figures))
        <div class="container-site home-diff__figures">
            <dl class="home-figs" data-home-figs>
                @foreach ($figures as $figure)
                    @php($value = (int) ($figure['value'] ?? 0))
                    @php($shown = $value >= 10000 ? number_format($value, 0, ',', "\u{202F}") : $value)
                    <div class="home-fig" data-home-fig>
                        <span class="home-fig__rule" aria-hidden="true"></span>
                        <dt class="home-fig__label">{{ $figure['label'] ?? '' }}</dt>
                        <dd class="home-fig__value">
                            <span aria-hidden="true"><span data-counter data-value="{{ $value }}">{{ $shown }}</span><span class="home-fig__suffix">{{ $figure['suffix'] ?? '' }}</span></span>
                            <span class="sr-only">{{ $shown }}{{ $figure['suffix'] ?? '' }}</span>
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endif
</section>
