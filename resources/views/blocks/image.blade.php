@php($asset = $assets->get($data['image_id'] ?? 0))

@if ($asset)
    <figure class="bg-paper-100 py-8 md:py-12">
        <div @class(['mx-auto w-full', 'max-w-3xl px-[var(--gutter)]' => ($data['size'] ?? 'wide') === 'normal', 'max-w-6xl px-[var(--gutter)]' => ($data['size'] ?? 'wide') === 'wide'])>
            <x-picture :asset="$asset" conversion="hero" sizes="(min-width: 1024px) 72rem, 100vw" class="h-auto w-full rounded-card" />
            @if (! empty($data['caption']) || $asset->caption)
                <figcaption class="mt-3 text-sm text-ink-600">{{ $data['caption'] ?? $asset->caption }}</figcaption>
            @endif
        </div>
    </figure>
@endif
