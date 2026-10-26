// Pages avec composants Livewire : Livewire fournit Alpine (build CSP) ; on y branche nos composants.
import { Alpine, Livewire } from '../../../vendor/livewire/livewire/dist/livewire.csp.esm';
import focus from '@alpinejs/focus';
import { registerComponents } from '../components/register.js';

export default function start() {
    window.Alpine = Alpine;
    Alpine.plugin(focus);
    registerComponents(Alpine);
    Livewire.start();
}
