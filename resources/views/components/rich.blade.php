@props(['html' => null, 'width' => 'normal'])

{{-- Texte riche assaini (liste blanche) : jamais de HTML brut venant d'un éditeur ou d'un import. --}}
<div {{ $attributes->class(['prose prose-lg prose-uaz max-w-none', 'prose-headings:scroll-mt-28']) }}>
    {{ \App\Support\Html::clean($html) }}
</div>
