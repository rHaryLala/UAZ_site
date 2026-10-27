{{--
    Grille de photos + visionneuse. À placer dans un élément x-data="lightbox".
    $photos : collection de ['asset' => MediaAsset, 'caption' => ?string]. Sans JS, chaque vignette ouvre l'image.
--}}
<ul class="grid grid-cols-2 gap-3 md:grid-cols-3 md:gap-4">
    @foreach ($photos as $photo)
        @php($asset = $photo['asset'])
        <li @class(['md:col-span-2 md:row-span-2' => $loop->first && $photos->count() > 4])>
            <a href="{{ $asset->url('hero') }}" data-lightbox-item data-alt="{{ $asset->alt }}" data-caption="{{ $photo['caption'] }}" class="group relative block aspect-[4/3] overflow-hidden rounded-xl bg-navy-800">
                <x-picture :asset="$asset" conversion="card" sizes="(min-width: 768px) 30vw, 46vw" class="size-full object-cover transition-transform duration-700 ease-out group-hover:scale-105" />
                <span class="absolute inset-0 bg-navy-900/0 transition group-hover:bg-navy-900/20"></span>
            </a>
        </li>
    @endforeach
</ul>

<dialog x-ref="dialog" aria-label="{{ $dialogLabel ?? 'Galerie' }}" @keydown.arrow-right="next" @keydown.arrow-left="prev" class="m-auto h-dvh max-h-none w-screen max-w-none overflow-hidden bg-navy-950/95 p-0 text-white backdrop:bg-navy-950 open:flex">
    <div class="relative flex size-full flex-col items-center justify-center p-4 md:p-10">
        <button type="button" @click="close" class="absolute top-4 right-4 inline-flex size-12 items-center justify-center rounded-none border border-white/40 hover:bg-white hover:text-navy-900 md:top-6 md:right-6" aria-label="Fermer la visionneuse"><x-ico name="close" class="size-5" /></button>
        <button type="button" @click="prev" class="absolute top-1/2 left-3 inline-flex size-12 -translate-y-1/2 items-center justify-center rounded-none border border-white/40 hover:bg-white hover:text-navy-900 md:left-6" aria-label="Photo précédente"><x-ico name="arrow-left" class="size-5" /></button>
        <button type="button" @click="next" class="absolute top-1/2 right-3 inline-flex size-12 -translate-y-1/2 items-center justify-center rounded-none border border-white/40 hover:bg-white hover:text-navy-900 md:right-6" aria-label="Photo suivante"><x-ico name="arrow-right" class="size-5" /></button>

        <img x-bind:src="current.src" x-bind:alt="current.alt" class="max-h-[78dvh] max-w-full rounded-lg object-contain">
        <p class="mt-4 max-w-2xl text-center text-sm text-navy-100"><span x-text="current.caption"></span> <span class="tabular-nums text-gold-300" x-text="counter" aria-live="polite"></span></p>
    </div>
</dialog>
