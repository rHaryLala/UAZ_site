<section class="on-dark relative isolate overflow-hidden bg-navy-800 py-16 text-white md:py-24">
    <x-madagascar class="pointer-events-none absolute top-1/2 -right-10 -z-10 h-[170%] -translate-y-1/2 text-navy-700/60" />
    <figure class="container-prose max-w-4xl" data-reveal>
        <blockquote class="font-serif text-h2 leading-snug text-white">
            <span class="text-gold-300" aria-hidden="true">«</span>&nbsp;{{ $data['text'] ?? '' }}&nbsp;<span class="text-gold-300" aria-hidden="true">»</span>
        </blockquote>
        @if (! empty($data['author']))
            <figcaption class="mt-6 text-sm"><span class="font-semibold text-white">{{ $data['author'] }}</span>@if (! empty($data['role'])) <span class="text-navy-100">— {{ $data['role'] }}</span>@endif</figcaption>
        @endif
    </figure>
</section>
