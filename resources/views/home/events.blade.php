<x-section :eyebrow="__('home.events.eyebrow')" :title="__('home.events.title')">
    <ul class="grid gap-5 md:grid-cols-3">
        @foreach ($events as $event)
            <li data-reveal data-reveal-delay="{{ $loop->index * 0.1 }}"><x-event-card :event="$event" /></li>
        @endforeach
    </ul>
    <p class="mt-8" data-reveal><a href="{{ url('/evenements') }}" class="link-arrow">{{ __('home.events.link') }} <x-ico name="arrow-right" class="size-4" /></a></p>
</x-section>
