@props([
    'eyebrow' => null,
    'title' => null,
    'intro' => null,
    'tone' => 'paper', // paper | white | sand | navy
    'align' => 'left', // left | center
    'level' => 'h2',
    'id' => null,
    'tight' => false,
])

@php
    $toneClasses = [
        'paper' => 'bg-paper-100',
        'white' => 'bg-paper-50',
        'sand' => 'bg-paper-200',
        'navy' => 'bg-navy-800 text-white on-dark',
    ][$tone] ?? 'bg-paper-100';
    $headingId = $id ? $id.'-title' : null;
@endphp

<section @if ($id) id="{{ $id }}" @endif @if ($headingId && $title) aria-labelledby="{{ $headingId }}" @endif {{ $attributes->class([$toneClasses, $tight ? 'py-12 md:py-16' : 'section-y']) }}>
    <div class="container-site">
        @if ($eyebrow || $title || $intro)
            <header @class(['mb-10 max-w-3xl md:mb-14', 'mx-auto text-center' => $align === 'center'])>
                @if ($eyebrow)
                    <p class="eyebrow mb-4" data-reveal>{{ $eyebrow }}</p>
                @endif
                @if ($title)
                    <{{ $level }} @if ($headingId) id="{{ $headingId }}" @endif @class(['text-h2', 'text-white!' => $tone === 'navy']) data-reveal>{{ $title }}</{{ $level }}>
                @endif
                @if ($intro)
                    <p @class(['mt-5 text-lead', 'text-ink-700' => $tone !== 'navy', 'text-navy-100' => $tone === 'navy']) data-reveal data-reveal-delay="0.1">{{ $intro }}</p>
                @endif
            </header>
        @endif

        {{ $slot }}
    </div>
</section>
