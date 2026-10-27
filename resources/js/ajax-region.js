// Navigation par filtres sans rechargement complet : dans une zone marquée `data-ajax-region`, les liens qui restent sur la
// même page (ex. /formations?niveau=licence) récupèrent la page, remplacent uniquement la zone et mettent à jour l'adresse
// (historique, boutons précédent/suivant). Sans JavaScript, ce sont de simples liens : la page se recharge normalement.
const SELECTOR = '[data-ajax-region]';
let controller = null;

function region() {
    return document.querySelector(SELECTOR);
}

async function load(url, { push }) {
    const current = region();
    if (!current) return;

    controller?.abort();
    controller = new AbortController();
    current.setAttribute('aria-busy', 'true');
    current.classList.add('ajax-loading');

    try {
        const response = await fetch(url, { headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal });
        if (!response.ok) throw new Error(String(response.status));

        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
        const next = doc.querySelector(SELECTOR);
        if (!next) throw new Error('zone introuvable');

        const focusedHref = document.activeElement?.closest(`${SELECTOR} a`)?.getAttribute('href');
        current.innerHTML = next.innerHTML;
        // Les éléments « data-reveal » du nouveau contenu s'affichent par un court fondu CSS (pas de GSAP à relancer).
        current.querySelectorAll('[data-reveal]').forEach((el, index) => {
            el.setAttribute('data-revealed', '');
            el.style.setProperty('--enter-delay', `${Math.min(index, 8) * 45}ms`);
            el.classList.add('ajax-enter');
        });
        document.title = doc.title;
        if (push) history.pushState({ ajax: true }, '', url);

        // Pagination : si le haut de la zone est hors écran, on y remonte.
        if (current.getBoundingClientRect().top < 0) {
            current.scrollIntoView({ block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
        }

        // Le lien qui avait le focus est remplacé : on le retrouve pour ne pas perdre la navigation au clavier.
        if (focusedHref) current.querySelector(`a[href="${CSS.escape(focusedHref)}"]`)?.focus({ preventScroll: true });
    } catch (error) {
        if (error.name === 'AbortError') return;
        window.location.href = url; // repli : chargement complet
    } finally {
        current.removeAttribute('aria-busy');
        current.classList.remove('ajax-loading');
    }
}

export function initAjaxRegion() {
    if (!region()) return;

    document.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest(`${SELECTOR} a[href]`) : null;
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        if (link.target && link.target !== '_self') return;

        const url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin || url.pathname !== window.location.pathname) return;

        event.preventDefault();
        if (url.href !== window.location.href) load(url.href, { push: true });
    });

    window.addEventListener('popstate', () => load(window.location.href, { push: false }));
}
