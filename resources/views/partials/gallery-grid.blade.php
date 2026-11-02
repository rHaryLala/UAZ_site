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

@include('partials.gallery-dialog', ['dialogLabel' => $dialogLabel ?? 'Galerie'])
