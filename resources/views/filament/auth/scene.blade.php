@php
    // De vraies photos de l'université, déjà publiées sur le site (réglages, pages, actualités) : voir App\Support\LoginPhotos.
    $photos = \App\Support\LoginPhotos::pick(6);
    // Trois colonnes de hauteurs inégales, remplies tour à tour.
    $columns = $photos->count() >= 3 ? $photos->groupBy(fn ($photo, $i) => $i % 3)->map(fn ($group) => $group->values()) : collect();
    $weights = [[3, 2], [2, 3], [2, 2.4]];
@endphp

{{-- La vitrine : des photos sombres, un « regard » (objectif) qui suit le pointeur et révèle les photos en couleurs ; il cligne quand on entre dans un champ,
     se ferme quand le mot de passe est visible, se secoue sur une erreur (animations : resources/js/admin-login.js). Décor : aucun texte à lire. --}}
<div class="uaz-scene" data-uaz-scene aria-hidden="true">
    @if ($columns->isNotEmpty())
        @foreach (['base', 'lit'] as $layer)
            <div class="uaz-gallery uaz-gallery--{{ $layer }}" data-uaz-gallery="{{ $layer }}">
                @foreach ($columns as $c => $items)
                    <div class="uaz-col" data-uaz-col="{{ $c }}">
                        @foreach ($items as $i => $photo)
                            <span class="uaz-tile" style="--g: {{ $weights[$c][$i % 2] }}" data-uaz-tile>
                                <x-picture :asset="$photo" conversion="card" sizes="(min-width: 1024px) 20vw, 34vw" decorative :eager="$layer === 'base' && $loop->parent->first && $loop->first" />
                            </span>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endforeach
        <div class="uaz-lens" data-uaz-lens><i></i><i></i><i></i><i></i></div>
    @else
        <div class="uaz-plain"><x-seal :size="160" /></div>
    @endif
    <div class="uaz-vignette"></div>

    <div class="uaz-corner uaz-corner--top">
        <x-seal :size="56" eager class="uaz-corner__seal" />
        <p><strong>Université Adventiste Zurcher</strong><span>Administration du site</span></p>
    </div>
    <p class="uaz-corner uaz-corner--motto">Spiritus · Manus · Mens</p>
</div>
