// Pages de connexion du back-office : la vitrine (voir resources/views/filament/auth/scene.blade.php et resources/css/admin-login.css).
//
// De vraies photos de l'université, sombres, qu'un « regard » (objectif) éclaire en couleurs. Il se comporte comme un œil :
//  - il suit le pointeur, et les photos s'inclinent doucement vers lui ;
//  - il cligne quand on entre dans un champ, et de temps en temps tout seul ;
//  - il se tourne vers le champ actif et suit la saisie de l'adresse e-mail le long du champ ;
//  - il s'ouvre plus grand à mesure que le formulaire se remplit ;
//  - il se ferme (et les photos s'estompent) quand le mot de passe est visible, et se rouvre quand il est masqué ;
//  - sur une erreur, les photos se secouent et le regard cligne ; à la connexion, il s'ouvre en grand.
//  - à l'arrivée : photos dévoilées par un rideau, regard qui s'ouvre, titre mot à mot, champs qui montent.
// Le panneau est une application à navigation SPA : la scène est montée au chargement et à chaque arrivée sur une page de connexion, démontée en la quittant.
// Avec « réduire les animations » : le regard reste fixe au centre ; il s'ouvre toujours avec la saisie et se ferme avec le mot de passe visible, sans transition.
import { gsap } from 'gsap';
import { SplitText } from 'gsap/SplitText';

gsap.registerPlugin(SplitText);

let teardown = null;

function mount() {
    const scene = document.querySelector('[data-uaz-scene]');
    if (!scene || scene.dataset.uazReady) return;
    scene.dataset.uazReady = '1';

    const $ = (selector, parent = document) => parent.querySelector(selector);
    const $$ = (selector, parent = document) => [...parent.querySelectorAll(selector)];
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const fine = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    const hasGallery = Boolean($('[data-uaz-lens]', scene));
    const clamp = gsap.utils.clamp;

    const box = () => scene.getBoundingClientRect();
    const base = () => Math.min(box().width, box().height) * (box().width > box().height ? 0.34 : 0.5);

    // L'état du regard : tout passe par ces sept nombres, écrits dans la scène (variables CSS) à chaque image.
    const rect0 = box();
    const state = { lx: rect0.width * 0.5, ly: rect0.height * 0.5, lr: base() * 0.85, lry: 1, shut: 0, px: 0, py: 0 };
    let written = '';
    const write = () => {
        const next = [state.lx, state.ly, state.lr, state.lry, state.shut, state.px, state.py].map((n) => n.toFixed(2)).join(' ');
        if (next === written) return;
        written = next;
        ['lx', 'ly', 'lr', 'lry', 'shut', 'px', 'py'].forEach((key) => scene.style.setProperty(`--${key}`, state[key].toFixed(2)));
    };
    write();

    const fields = () => $$('.fi-simple-main form input:not([type=checkbox]):not([type=hidden]):not([type=radio])');
    const progress = () => {
        const all = fields();

        return all.length ? all.filter((input) => input.value.trim() !== '').length / all.length : 0;
    };
    const passwordInput = () => $('.fi-simple-main input[id*="password"]');
    const isShut = () => passwordInput()?.type === 'text';

    const cleanups = [];
    const ctx = gsap.context(() => {
        if (hasGallery && !reduce) gsap.ticker.add(write);
        if (!reduce) cleanups.push(() => gsap.ticker.remove(write));
    }, scene);

    /** Le regard se tourne vers un point de l'écran. */
    const lookAt = (clientX, clientY, duration = 0.7) => {
        if (!hasGallery || reduce || isShut()) return;
        const r = box();
        gsap.to(state, {
            lx: clamp(0, r.width, clientX - r.left),
            ly: clamp(0, r.height, clientY - r.top),
            px: (0.5 - clientX / window.innerWidth) * 26,
            py: (0.5 - clientY / window.innerHeight) * 18,
            duration,
            ease: 'power3.out',
            overwrite: 'auto',
        });
    };

    const openness = () => base() * (0.85 + 0.5 * progress());
    const settleRadius = (duration = 1) => {
        if (!hasGallery) return;
        if (reduce) {
            state.lr = openness();
            write();

            return;
        }
        gsap.to(state, { lr: openness(), duration, ease: 'power2.out', overwrite: 'auto' });
    };

    /** Un clignement : la paupière (l'ouverture verticale) se ferme et se rouvre. */
    const blink = (times = 1) => {
        if (!hasGallery || reduce || isShut()) return;
        const tl = gsap.timeline({ overwrite: 'auto' });
        for (let i = 0; i < times; i++) tl.to(state, { lry: 0.04, duration: 0.09, ease: 'power2.in' }).to(state, { lry: 1, duration: 0.2, ease: 'power2.out' });
    };

    /** Mot de passe visible : le regard se ferme et les photos s'estompent. Masqué : il se rouvre. */
    const applyShut = () => {
        if (!hasGallery) return;
        const on = isShut();
        if (reduce) {
            Object.assign(state, { lry: on ? 0.03 : 1, shut: on ? 1 : 0 });
            write();

            return;
        }
        gsap.killTweensOf(state, 'lry,shut');
        gsap.to(state, { lry: on ? 0.03 : 1, shut: on ? 1 : 0, duration: on ? 0.35 : 0.5, ease: 'power3.inOut' });
    };

    // Le regard suit le pointeur (souris), ou dérive doucement sur un écran tactile.
    if (hasGallery && !reduce) {
        if (fine) {
            const onMove = (event) => lookAt(event.clientX, event.clientY);
            const onLeave = () => {
                const r = box();
                lookAt(r.left + r.width / 2, r.top + r.height / 2, 1.2);
            };
            window.addEventListener('pointermove', onMove);
            document.documentElement.addEventListener('pointerleave', onLeave);
            cleanups.push(() => {
                window.removeEventListener('pointermove', onMove);
                document.documentElement.removeEventListener('pointerleave', onLeave);
            });
        } else {
            const wander = gsap.delayedCall(0.4, function drift() {
                const r = box();
                lookAt(r.left + r.width * gsap.utils.random(0.2, 0.8), r.top + r.height * gsap.utils.random(0.2, 0.8), 2.2);
                wander.duration(gsap.utils.random(3, 5)).restart(true);
            });
            cleanups.push(() => wander.kill());
        }

        // Il cligne aussi de temps en temps, tout seul.
        const idle = gsap.delayedCall(4, function again() {
            if (!document.hidden) blink(Math.random() < 0.2 ? 2 : 1);
            idle.duration(gsap.utils.random(2.5, 6)).restart(true);
        });
        cleanups.push(() => idle.kill());
    }

    // Entrer dans un champ : clignement, puis le regard se tourne vers lui.
    const onFocus = (event) => {
        const input = event.target;
        if (!(input instanceof HTMLInputElement) || !input.closest('.fi-simple-main')) return;
        blink();
        const r = input.getBoundingClientRect();
        if (!fine) lookAt(box().left + box().width * 0.8, r.top + r.height / 2);
    };
    document.addEventListener('focusin', onFocus);

    // Saisie : le regard suit le texte de l'adresse e-mail le long du champ, et s'ouvre avec ce qui est rempli.
    const onInput = (event) => {
        const input = event.target;
        if (input instanceof HTMLInputElement && input.type !== 'password' && !isShut() && !fine) {
            const r = input.getBoundingClientRect();
            const ratio = Math.min(1, input.value.length / 28);
            lookAt(box().left + box().width * (0.55 + 0.4 * ratio), r.top + r.height / 2, 0.3);
        }
        settleRadius();
        applyShut();
    };
    document.addEventListener('input', onInput);

    // Le bouton « œil » de Filament change le type du champ : on le surveille.
    const typeObserver = new MutationObserver(applyShut);
    const main = $('.fi-simple-main');
    if (main) typeObserver.observe(main, { attributes: true, attributeFilter: ['type'], subtree: true });

    // À l'envoi : le regard s'ouvre. Sur une erreur de Filament : photos secouées, regard qui cligne ; à la connexion : il s'ouvre en grand.
    const onSubmit = () => {
        if (hasGallery && !reduce) gsap.to(state, { lr: base() * 1.9, duration: 0.8, ease: 'power2.out', overwrite: 'auto' });
    };
    document.addEventListener('submit', onSubmit, true);

    let errors = $$('.fi-fo-field-wrp-error-message').length;
    const errorObserver = new MutationObserver(() => {
        const now = $$('.fi-fo-field-wrp-error-message').length;
        if (now > errors && hasGallery && !reduce) {
            gsap.fromTo($$('[data-uaz-gallery]', scene), { x: 0 }, { keyframes: { x: [-18, 14, -10, 6, 0] }, duration: 0.6, ease: 'power2.out' });
            settleRadius(0.6);
            blink(2);
        }
        errors = now;
    });
    if (main) errorObserver.observe(main, { childList: true, subtree: true });

    const onNavigate = () => {
        if (hasGallery && !reduce) gsap.to(state, { lr: base() * 3, lry: 1, shut: 0, duration: 0.8, ease: 'power2.in', overwrite: 'auto' });
    };
    document.addEventListener('livewire:navigate', onNavigate);

    // Arrivée : photos dévoilées par un rideau, regard qui s'ouvre, titre mot à mot, champs qui montent.
    if (!reduce) {
        ctx.add(() => {
            const tiles = $$('[data-uaz-tile]', scene);
            if (tiles.length) {
                const half = tiles.length / 2;
                gsap.from(tiles, { clipPath: 'inset(0% 0% 100% 0%)', duration: 1.2, ease: 'power4.inOut', stagger: (i) => (i % half) * 0.09 });
            }
            if (hasGallery) {
                const target = { lr: state.lr };
                state.lr = 0;
                state.lry = 0.04;
                gsap.to(state, { lr: target.lr, duration: 1.6, delay: 0.5, ease: 'power3.out' });
                gsap.to(state, { lry: 1, duration: 0.7, delay: 0.7, ease: 'power3.out' });
            }
            gsap.from($$('.uaz-corner', scene), { autoAlpha: 0, y: 14, duration: 1, delay: 0.8, stagger: 0.12, ease: 'power3.out' });

            const heading = $('.fi-simple-header-heading');
            if (heading) {
                SplitText.create(heading, {
                    type: 'words',
                    wordsClass: 'word',
                    autoSplit: true,
                    onSplit: (self) => gsap.from(self.words, { yPercent: 70, opacity: 0, duration: 0.9, stagger: 0.07, delay: 0.5, ease: 'power3.out' }),
                });
            }
            // fromTo (et non from) : le bouton d'envoi de Filament est masqué au moment où l'animation se crée, « from » le laisserait invisible.
            gsap.fromTo($$('.fi-simple-main form .fi-fo-field, .fi-simple-main form button[type=submit]'), { autoAlpha: 0, y: 24 }, { autoAlpha: 1, y: 0, duration: 0.8, delay: 0.8, stagger: 0.1, ease: 'power3.out', clearProps: 'opacity,visibility,transform' });
        });
    }

    teardown = () => {
        document.removeEventListener('focusin', onFocus);
        document.removeEventListener('input', onInput);
        document.removeEventListener('submit', onSubmit, true);
        document.removeEventListener('livewire:navigate', onNavigate);
        typeObserver.disconnect();
        errorObserver.disconnect();
        cleanups.forEach((undo) => undo());
        gsap.killTweensOf(state);
        ctx.revert();
        teardown = null;
    };
}

function sync() {
    if (document.querySelector('[data-uaz-scene]')) mount();
    else teardown?.();
}

document.addEventListener('DOMContentLoaded', sync);
document.addEventListener('livewire:navigated', sync);
if (document.readyState !== 'loading') sync();
