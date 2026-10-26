@php($cards = collect($data['cards'] ?? []))

@if ($cards->isNotEmpty())
    <x-section tone="sand" :title="$data['title'] ?? null" :intro="$data['intro'] ?? null" :tight="true">
        <ul class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($cards as $card)
                @php
                    $asset = $assets->get($card['image_id'] ?? 0);
                    $cardUrl = $card['url'] ?? null;
                    $safe = $cardUrl && preg_match('#^(/|https?://|mailto:|tel:)#i', $cardUrl) ? $cardUrl : null;
                @endphp
                <li data-reveal data-reveal-delay="{{ ($loop->index % 3) * 0.08 }}">
                    <article class="card group h-full">
                        @if ($asset)
                            <div class="aspect-[3/2] overflow-hidden"><x-picture :asset="$asset" conversion="card" sizes="(min-width: 1024px) 28rem, 92vw" class="size-full object-cover transition-transform duration-700 group-hover:scale-105" /></div>
                        @endif
                        <div class="p-7">
                            <h3 class="text-h4 font-serif">@if ($safe)<a href="{{ $safe }}" class="stretched-link hover:text-navy-600">{{ $card['title'] ?? '' }}</a>@else{{ $card['title'] ?? '' }}@endif</h3>
                            @if (! empty($card['text']))<p class="mt-3 text-sm text-ink-700">{{ $card['text'] }}</p>@endif
                            @if ($safe)<span class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-navy-800" aria-hidden="true">{{ $card['link_label'] ?? 'En savoir plus' }} <x-ico name="arrow-right" class="size-4" /></span>@endif
                        </div>
                    </article>
                </li>
            @endforeach
        </ul>
    </x-section>
@endif
