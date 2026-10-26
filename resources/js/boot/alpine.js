// Pages sans Livewire : Alpine (build CSP) seul.
import Alpine from '@alpinejs/csp';
import focus from '@alpinejs/focus';
import { registerComponents } from '../components/register.js';

export default function start() {
    window.Alpine = Alpine;
    Alpine.plugin(focus);
    registerComponents(Alpine);
    Alpine.start();
}
