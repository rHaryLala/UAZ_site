<section class="bg-paper-50 py-14 md:py-20" aria-labelledby="partners-title">
    <div class="container-site">
        <h2 id="partners-title" class="eyebrow mb-8 text-center" data-reveal>{{ __('home.partners.title') }}</h2>
        <ul class="flex flex-wrap items-center justify-center gap-x-12 gap-y-8">
            @foreach ($partners as $partner)
                <li data-reveal data-reveal-delay="{{ $loop->index * 0.05 }}">
                    @if ($partner->url)
                        <a href="{{ $partner->url }}" target="_blank" rel="noopener noreferrer" class="block" aria-label="{{ __(':name (nouvel onglet)', ['name' => $partner->name]) }}">
                            @if ($partner->logo)<x-picture :asset="$partner->logo" conversion="thumb" sizes="160px" :alt="$partner->name" class="h-12 w-auto max-w-40 object-contain opacity-70 grayscale transition hover:opacity-100 hover:grayscale-0 md:h-14" />@else<span class="font-serif text-xl text-navy-800">{{ $partner->name }}</span>@endif
                        </a>
                    @elseif ($partner->logo)
                        <x-picture :asset="$partner->logo" conversion="thumb" sizes="160px" :alt="$partner->name" class="h-12 w-auto max-w-40 object-contain opacity-70 grayscale md:h-14" />
                    @else
                        <span class="font-serif text-xl text-navy-800">{{ $partner->name }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</section>
