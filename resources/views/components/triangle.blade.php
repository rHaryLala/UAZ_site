@props(['filled' => false])

{{-- Triangle du sceau (pointe en bas) : motif graphique décoratif, trait constant quelle que soit la taille. --}}
<svg {{ $attributes->merge(['class' => 'text-current']) }} viewBox="0 0 100 90" fill="{{ $filled ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.25" stroke-linejoin="miter" aria-hidden="true" focusable="false">
    <path d="M3 4h94L50 86z" vector-effect="non-scaling-stroke"/>
</svg>
