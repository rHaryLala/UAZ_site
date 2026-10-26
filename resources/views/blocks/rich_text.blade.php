<section class="bg-paper-100 py-12 md:py-16">
    <div @class(['mx-auto w-full px-[var(--gutter)]', 'max-w-3xl' => ($data['width'] ?? 'normal') === 'normal', 'max-w-6xl' => ($data['width'] ?? 'normal') === 'wide'])>
        @if (! empty($data['heading']))
            <h2 class="mb-6 text-h2" data-reveal>{{ $data['heading'] }}</h2>
        @endif
        <x-rich :html="$data['content'] ?? ''" />
    </div>
</section>
