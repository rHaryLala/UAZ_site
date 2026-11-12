/* Messages de l'atelier (window.Toast.success / error / warning / info) : une pastille en bas de l'écran qui disparaît seule. */
(() => {
    const zone = document.createElement('div');
    zone.className = 'rp-toasts';
    zone.setAttribute('role', 'status');
    zone.setAttribute('aria-live', 'polite');
    document.addEventListener('DOMContentLoaded', () => document.body.append(zone));
    const show = (type) => (html, options = {}) => {
        const el = document.createElement('div');
        el.className = 'rp-toast rp-toast--' + type;
        el.innerHTML = html;
        const close = () => { el.classList.add('rp-toast--sortie'); setTimeout(() => el.remove(), 250); };
        el.addEventListener('click', close);
        zone.append(el);
        setTimeout(close, options.duration || (type === 'error' ? 9000 : 4500));
        return el;
    };
    window.Toast = { success: show('success'), error: show('error'), warning: show('warning'), info: show('info') };
})();
