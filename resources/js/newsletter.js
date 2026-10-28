// Fenêtre « Lettre d'information » : s'ouvre seule à la première visite, puis de nouveau après 20 jours tant que la personne ne s'est pas
// abonnée (mémoire dans le navigateur : localStorage, aucun cookie). Se déclenche aussi depuis les boutons [data-newsletter-open]
// (« S'abonner » de la barre du haut et du menu). L'envoi se fait en JSON, sans recharger la page.
const KEY = 'uaz.newsletter';
const AUTO_OPEN_DELAY = 6000;

/** Lit l'état mémorisé ; null si le navigateur interdit le stockage (dans ce cas, pas d'ouverture automatique : on ne veut pas harceler). */
function readState() {
    try {
        const raw = JSON.parse(window.localStorage.getItem(KEY) || '{}');

        return raw && typeof raw === 'object' ? raw : {};
    } catch (error) {
        return null;
    }
}

function writeState(patch) {
    try {
        window.localStorage.setItem(KEY, JSON.stringify({ ...(readState() ?? {}), ...patch }));
    } catch (error) {
        // Stockage indisponible : sans conséquence, la fenêtre ne s'ouvrira simplement pas toute seule.
    }
}

export function shouldAutoOpen(state, delayDays, now = Date.now()) {
    if (state === null || state.subscribed) return false;

    return !state.seenAt || now - state.seenAt > delayDays * 86400000;
}

export function initNewsletter() {
    const dialog = document.querySelector('[data-newsletter-dialog]');
    if (!dialog || typeof dialog.showModal !== 'function') return;

    const form = dialog.querySelector('[data-newsletter-form]');
    const formWrap = dialog.querySelector('[data-newsletter-form-wrap]');
    const done = dialog.querySelector('[data-newsletter-done]');
    const error = dialog.querySelector('[data-newsletter-error]');
    const submit = dialog.querySelector('[data-newsletter-submit]');
    const input = form.querySelector('input[name=email]');
    const delayDays = Number(dialog.dataset.delayDays) || 20;

    const lock = (open) => {
        document.documentElement.classList.toggle('profile-open', open);
        window.dispatchEvent(new CustomEvent('uaz:search', { detail: { open } }));
    };

    const show = (message) => {
        error.textContent = message ?? '';
        error.hidden = !message;
    };

    const open = () => {
        if (dialog.open) return;
        formWrap.hidden = false;
        done.hidden = true;
        show('');
        dialog.showModal();
        lock(true);
        writeState({ seenAt: Date.now() });
        input.focus();
    };

    document.querySelectorAll('[data-newsletter-open]').forEach((trigger) => trigger.addEventListener('click', open));
    dialog.querySelectorAll('[data-newsletter-close]').forEach((button) => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
    dialog.addEventListener('close', () => lock(false));

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        show('');

        if (!input.value.trim()) {
            show('Saisissez votre adresse e-mail.');
            input.focus();

            return;
        }

        submit.disabled = true;
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const json = await response.json().catch(() => ({}));

            if (response.ok) {
                writeState({ subscribed: true, subscribedAt: Date.now() });
                dialog.querySelector('[data-newsletter-message]').textContent = json.message ?? '';
                formWrap.hidden = true;
                done.hidden = false;
                form.reset();
            } else if (response.status === 422) {
                show(json.errors?.email?.[0] ?? 'Adresse e-mail invalide.');
                input.focus();
            } else if (response.status === 429) {
                show('Trop de tentatives. Réessayez dans une minute.');
            } else {
                show(json.message ?? 'Une erreur est survenue. Réessayez dans quelques instants.');
            }
        } catch (failure) {
            show('Connexion impossible. Vérifiez votre réseau et réessayez.');
        } finally {
            submit.disabled = false;
        }
    });

    // Ouverture automatique : première visite, ou 20 jours après la dernière fois. Jamais par-dessus une autre fenêtre ni sur le Yearbook (plein écran).
    if (!document.querySelector('[data-yearbook]') && shouldAutoOpen(readState(), delayDays)) {
        window.setTimeout(() => {
            if (!document.querySelector('dialog[open]') && !document.documentElement.classList.contains('profile-open')) open();
        }, AUTO_OPEN_DELAY);
    }
}
