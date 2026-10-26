@props([
    'href' => null,
    'variant' => 'navy', // gold | navy | outline
    'size' => 'md', // md | sm
    'arrow' => false,
    'type' => 'button',
])

@php
    $classes = 'btn btn-'.$variant.($size === 'sm' ? ' btn-sm' : '');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
        @if ($arrow)<x-ico name="arrow-right" />@endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
        @if ($arrow)<x-ico name="arrow-right" />@endif
    </button>
@endif
