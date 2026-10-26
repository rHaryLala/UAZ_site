@if ($domains->isNotEmpty())
    <x-section id="formations" tone="white" :eyebrow="__('home.formations.eyebrow')" :title="__('home.formations.title')">
        <div class="-mt-6 mb-10 md:-mt-8 md:mb-14" data-reveal>
            <a href="{{ url('/formations') }}" class="link-arrow">{{ __('home.formations.link') }} <x-ico name="arrow-right" class="size-4" /></a>
        </div>

        <ul class="grid gap-6 md:grid-cols-2">
            @foreach ($domains as $domain)
                <li data-reveal data-reveal-delay="{{ ($loop->index % 2) * 0.1 }}">
                    <article class="card on-dark group relative isolate flex min-h-[22rem] flex-col justify-end overflow-hidden bg-navy-900 text-white md:min-h-[26rem]">
                        @if ($domain->cover)
                            <x-picture :asset="$domain->cover" conversion="card" sizes="(min-width: 768px) 45vw, 92vw" decorative
                                class="absolute inset-0 -z-10 size-full object-cover opacity-75 transition-transform duration-[1400ms] ease-out group-hover:scale-105" />
                        @else
                            <x-madagascar class="absolute -right-8 -bottom-8 -z-10 h-[125%] text-navy-800 transition-transform duration-[1400ms] ease-out group-hover:scale-105 group-hover:-rotate-3" />
                            <x-triangle class="absolute -top-10 -left-10 -z-10 w-72 text-gold-300/20 transition-transform duration-[1400ms] ease-out group-hover:rotate-6" />
                        @endif
                        <div class="absolute inset-0 -z-10 bg-gradient-to-t from-navy-950 via-navy-900/65 to-transparent" aria-hidden="true"></div>

                        <div class="p-7 md:p-9">
                            <p class="eyebrow mb-3">{{ $domain->type->getLabel() }}</p>
                            <h3 class="text-h3 text-white!">{{ $domain->name }}</h3>
                            <ul class="mt-4 space-y-1.5 text-sm text-navy-100">
                                @foreach ($domain->formations as $formation)
                                    <li><a href="{{ $formation->url() }}" class="relative z-10 inline-flex items-center gap-2 underline-offset-4 hover:text-white hover:underline">
                                        <x-ico name="chevron-right" class="size-3.5 text-gold-300" />{{ $formation->mention ?: $formation->title }}
                                    </a></li>
                                @endforeach
                            </ul>
                        </div>
                    </article>
                </li>
            @endforeach
        </ul>

        <div class="mt-10 flex flex-wrap items-center gap-x-8 gap-y-3 border-t border-paper-300 pt-6 text-sm text-ink-700" data-reveal>
            <p class="font-medium text-ink-900">{{ __('home.formations.paths') }}</p>
            @foreach ($levels as $item)
                <a href="{{ url('/formations?niveau='.$item->level->value) }}" class="font-semibold text-navy-800 underline decoration-gold-500 decoration-2 underline-offset-4 hover:decoration-navy-800">
                    {{ $item->level->getLabel() }}@if ($item->duration) · {{ $item->duration }}@endif @if ($item->credits) · {{ $item->credits }} crédits ECTS @endif
                </a>
            @endforeach
            <span>{{ __('home.formations.continuing') }}</span>
        </div>
    </x-section>
@endif
