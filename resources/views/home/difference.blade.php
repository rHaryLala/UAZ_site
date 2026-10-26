@php($figures = $site->key_figures ?? [])

<x-section id="difference" :eyebrow="__('home.difference.eyebrow')">
    <div class="grid gap-12 lg:grid-cols-[1.1fr_1fr] lg:gap-20">
        <div>
            <h2 class="text-h2" data-reveal>{{ __('home.difference.title') }}</h2>
        </div>
        <div class="space-y-6 self-end">
            <p class="text-lead text-ink-700" data-reveal data-reveal-delay="0.1">{{ __('home.difference.text') }}</p>
            <a href="{{ url('/a-propos') }}" class="link-arrow" data-reveal data-reveal-delay="0.2">{{ __('home.difference.link') }} <x-ico name="arrow-right" class="size-4" /></a>
        </div>
    </div>

    @if (count($figures))
        <div class="mt-16 lg:mt-24"><x-key-figures :figures="$figures" /></div>
    @endif
</x-section>
