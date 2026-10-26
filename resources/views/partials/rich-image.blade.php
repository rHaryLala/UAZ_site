{{-- Image insérée dans un texte riche (importée de WordPress ou ajoutée au back-office) : responsive, sans décalage de mise en page. --}}
<figure class="not-prose my-8">
    <x-picture :asset="$asset" conversion="hero" sizes="(min-width: 768px) 46rem, 92vw" class="h-auto w-full rounded-card" />
    @if (filled($caption) || filled($asset->caption))
        <figcaption class="mt-2 text-sm text-ink-600">{{ filled($caption) ? strip_tags($caption) : $asset->caption }}</figcaption>
    @endif
</figure>
