<x-filament-widgets::widget>
    <div style="display:grid; gap:.75rem; grid-template-columns:repeat(auto-fit, minmax(14rem, 1fr))">
        @foreach ($this->getShortcuts() as $shortcut)
            <a href="{{ $shortcut['url'] }}" wire:navigate
               class="fi-quick-action"
               style="display:flex; align-items:center; gap:.875rem; padding:1rem 1.125rem; border-radius:.75rem; border:1px solid color-mix(in srgb, currentColor 16%, transparent); background:color-mix(in srgb, currentColor 5%, transparent); text-decoration:none; transition:border-color .15s, transform .15s"
               onmouseover="this.style.borderColor='var(--uaz-accent, var(--primary-500))'; this.style.transform='translateY(-1px)'"
               onmouseout="this.style.borderColor=''; this.style.transform=''">
                <span style="display:flex; align-items:center; justify-content:center; width:2.5rem; height:2.5rem; flex:none; border-radius:.625rem; background:var(--uaz-accent, var(--primary-600)); color:var(--primary-foreground, #fff)">
                    <x-filament::icon :icon="$shortcut['icon']" style="width:1.25rem; height:1.25rem" />
                </span>
                <span style="min-width:0">
                    <span style="display:block; font-weight:600">{{ $shortcut['label'] }}</span>
                    <span style="display:block; font-size:.8125rem; opacity:.7">{{ $shortcut['hint'] }}</span>
                </span>
            </a>
        @endforeach
    </div>
</x-filament-widgets::widget>
