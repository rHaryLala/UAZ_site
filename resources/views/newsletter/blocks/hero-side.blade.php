{{-- Le côté libre de « Photo en fond » : le badge rond (en haut) et, sur le plan de travail, le repère « choisir la photo » tant qu'il n'y en a pas. --}}
@if ($showPick)
    @include('newsletter.blocks.hero-pick')
    @if ($hasBadge) <div style="height:10px;line-height:10px;font-size:0;">&nbsp;</div> @endif
@endif
@if ($hasBadge)
    @include('newsletter.blocks.hero-badge')
@endif
