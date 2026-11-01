@props(['blocks' => []])

{{-- Rendu des blocs flexibles d'une page : chaque type a sa vue resources/views/blocks/{type}.blade.php. --}}
@php
    $known = \App\Filament\Support\PageBlocks::TYPES;
    // Les images référencées par les blocs sont chargées en une requête (pas de N+1).
    $ids = collect($blocks)->flatMap(fn ($b) => match ($b['type'] ?? null) {
        'image' => [$b['data']['image_id'] ?? null],
        'gallery' => collect($b['data']['images'] ?? [])->pluck('image_id')->all(),
        'download_list' => collect($b['data']['items'] ?? [])->pluck('file_id')->all(),
        'card_list' => collect($b['data']['cards'] ?? [])->pluck('image_id')->all(),
        default => [],
    })->filter()->unique()->all();
    $assets = $ids ? \App\Models\MediaAsset::with('media')->whereIn('id', $ids)->get()->keyBy('id') : collect();
@endphp

@foreach ($blocks ?? [] as $block)
    @continue(! in_array($block['type'] ?? null, $known, true))
    {{-- Éditeur visuel : chaque bloc est enveloppé pour pouvoir être sélectionné (jamais présent sur le site public). --}}
    @if (\App\Support\EditMode::on())<div data-ve-block="{{ $loop->index }}" data-ve-type="{{ $block['type'] }}">@endif
    @include('blocks.'.$block['type'], ['data' => $block['data'] ?? [], 'assets' => $assets])
    @if (\App\Support\EditMode::on())</div>@endif
@endforeach
