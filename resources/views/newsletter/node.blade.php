{{--
    Un bloc de la newsletter, avec son enveloppe. Niveau 0 : une ligne du tableau de la newsletter (marges latérales de 32 px, sauf les blocs « à bord perdu »).
    Niveaux 1 et 2 : un bloc dans une colonne ou un bandeau (la place est déjà réservée par le conteneur).
    $block : le bloc ; $depth : niveau ; $width : place disponible en px ; $ctx : thème, police, images, mode d'édition (voir NewsletterRenderer::context).
--}}
@php
    $d = $block['data'];
    $meta = \App\Support\NewsletterBlocks::types()[$block['type']];
    $ed = fn (string $field): string => $editing ? 'data-nl-field="'.$field.'"' : '';
    $isSection = $block['type'] === 'section';
    // Un bloc « à bord perdu » (espace, bandeau non inséré) touche les bords de la newsletter ; les autres gardent leurs marges.
    $flush = ! empty($meta['bleed']) || ($isSection && $d['inset'] === '');
    $pad = $depth === 0 ? ($flush ? '0' : '14px '.\App\Support\NewsletterRenderer::SIDE.'px') : (! empty($meta['bleed']) ? '0' : '8px 0');
    $marks = $editing ? 'data-nl-block data-nl-type="'.$block['type'].'" data-nl-id="'.$block['id'].'" data-nl-depth="'.$depth.'"' : '';
    // La place réellement offerte au contenu : une section insérée ou en colonne n'a pas la largeur de la newsletter.
    $room = $depth === 0 && $flush ? \App\Support\NewsletterRenderer::WIDTH : $width;
@endphp
@if ($depth === 0)
    <tr {!! $editing ? 'data-nl-row' : '' !!}>
        <td class="{{ $flush ? '' : 'nl-pad' }}" {!! $marks !!} style="padding:{{ $pad }};">
            @include('newsletter.blocks.'.$block['type'], ['d' => $d, 'width' => $room])
        </td>
    </tr>
@else
    <div {!! $marks !!} style="padding:{{ $pad }};">
        @include('newsletter.blocks.'.$block['type'], ['d' => $d])
    </div>
@endif
