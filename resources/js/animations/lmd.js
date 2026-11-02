// Page « Système LMD » : cinq scènes (voir resources/views/pages/lmd.blade.php).
//
//  1. Les trois lettres : le titre se lève lettre à lettre ; L, M, D géantes suivent le pointeur à des profondeurs différentes et s'allument au survol.
//  2. La montée        : écran arrêté (grand écran) ; un marqueur gravit l'escalier marche après marche, la jauge de crédits ECTS se remplit
//                        (180 pour la Licence, puis 300) et son compteur monte ; le texte de chaque grade remplace le précédent.
//  3. La Licence       : les mentions se posent par vagues.      4. Le Master : M1 puis M2 se relient, les mentions arrivent.
//  5. Le Doctorat      : les mots de la phrase arrivent tour à tour.
//
// Renforcement progressif : sans JavaScript, avec « réduire les animations » ou sur petit écran, la page reste complète (voir resources/css/lmd.css) :
// les trois grades sont lisibles l'un sous l'autre, l'escalier et la jauge sont dessinés en entier.
import { DrawSVGPlugin } from 'gsap/DrawSVGPlugin';
import { gsap, ScrollTrigger, SplitText, MOTION_OK } from './gsap.js';

gsap.registerPlugin(DrawSVGPlugin);

const headerHeight = () => document.querySelector('.site-header')?.offsetHeight ?? 0;
const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

export function initLmd() {
    const mm = gsap.matchMedia();

    mm.add(MOTION_OK, (context) => {
        const run = () => {
            document.documentElement.classList.add('lmd-ready');
            hero();
            climb();
            mentions();
            stages();
            doctorate();
            ScrollTrigger.refresh();
        };

        // Les polices doivent être chargées avant de découper le texte en lettres et en mots.
        if (document.fonts?.status === 'loaded') run();
        else document.fonts.ready.then(() => context.add(run));
    });
}

/* ───────── 1. Les trois lettres ───────── */
function hero() {
    const root = $('[data-lmd-hero]');
    if (!root) return;

    SplitText.create($('[data-lmd-title]', root), {
        type: 'words,chars',
        wordsClass: 'word',
        charsClass: 'char',
        autoSplit: true,
        onSplit: (self) => gsap.from(self.chars, { yPercent: 110, opacity: 0, duration: 1, stagger: 0.04, ease: 'power4.out' }),
    });
    gsap.from($$('[data-lmd-fade]', root), { autoAlpha: 0, y: 22, duration: 1, delay: 0.6, stagger: 0.12, ease: 'power3.out' });

    const letters = $$('[data-lmd-letter]', root);
    gsap.from(letters, { y: 80, opacity: 0, duration: 1.2, delay: 0.5, stagger: 0.15, ease: 'power3.out' });

    // Chaque lettre suit le pointeur, d'autant plus qu'elle est « proche » (profondeur 1, 2, 3).
    if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        const moves = letters.map((letter, i) => {
            const big = $('.lmd-letter__big', letter);

            return [gsap.quickTo(big, 'x', { duration: 0.9, ease: 'power3.out' }), gsap.quickTo(big, 'y', { duration: 0.9, ease: 'power3.out' }), (i + 1) * 9];
        });
        root.addEventListener('pointermove', (event) => {
            const rect = root.getBoundingClientRect();
            const dx = (event.clientX - rect.left) / rect.width - 0.5;
            const dy = (event.clientY - rect.top) / rect.height - 0.5;
            moves.forEach(([x, y, amount]) => {
                x(dx * amount);
                y(dy * amount);
            });
        });
        root.addEventListener('pointerleave', () => moves.forEach(([x, y]) => (x(0), y(0))));
    }
}

/* ───────── 2. La montée ───────── */
function climb() {
    const root = $('[data-lmd-climb]');
    const stage = $('[data-lmd-stage]', root ?? document);
    if (!root || !stage) return;

    const steps = $$('[data-lmd-step]', root);
    const stair = $('[data-lmd-stair]', root);
    const next = $('[data-lmd-next]', root);
    const marker = $('[data-lmd-marker]', root);
    const arcLicence = $('[data-lmd-arc="licence"]', root);
    const arcMaster = $('[data-lmd-arc="master"]', root);
    const count = $('[data-lmd-count]', root);
    const gauge = $('.lmd-gauge', root);
    const share = Number(gauge?.dataset.share ?? 60);
    const total = Number(count?.dataset.final ?? 300);
    const licence = Number(count?.dataset.licence ?? 180);
    const length = stair?.getTotalLength?.() ?? 0;
    if (!steps.length || !stair || !marker) return;

    // Positions de l'escalier (en fraction de sa longueur) : fin de la 1re marche + contremarche, fin de la 2e, fin de la 3e.
    const reach = [0.378, 0.757, 1];
    const state = { p: 0, ects: 0 };
    const place = () => {
        const point = stair.getPointAtLength(state.p * length);
        marker.setAttribute('cx', point.x);
        marker.setAttribute('cy', point.y);
        gsap.set(stair, { drawSVG: `0% ${state.p * 100}%` });
        if (count) count.textContent = String(Math.round(state.ects));
    };

    const mm = gsap.matchMedia();

    // Grand écran : la page s'arrête, le marqueur gravit l'escalier.
    mm.add('(min-width: 1024px)', () => {
        root.classList.add('is-pinned');
        gsap.set(next, { drawSVG: '0%' });
        gsap.set([arcLicence, arcMaster], { strokeDasharray: '0 100' });
        gsap.set(steps, { autoAlpha: 0, y: 30 });
        gsap.set(steps[0], { autoAlpha: 1, y: 0 });
        place();

        const tl = gsap.timeline({
            defaults: { ease: 'none' },
            scrollTrigger: {
                trigger: stage,
                start: () => `top top+=${headerHeight()}`,
                end: () => `+=${Math.round(window.innerHeight * 2.6)}`,
                pin: true,
                scrub: 0.6,
                anticipatePin: 1,
                invalidateOnRefresh: true,
            },
        });

        // La Licence : première marche, 180 crédits.
        tl.to(state, { p: reach[0], ects: licence, duration: 1, onUpdate: place }, 0)
            .to(arcLicence, { strokeDasharray: `${share} 100`, duration: 1 }, 0)
            // Le Master : deuxième marche, 300 crédits depuis le Bac.
            .to(steps[0], { autoAlpha: 0, y: -30, duration: 0.25 }, 1.05)
            .fromTo(steps[1], { autoAlpha: 0, y: 30 }, { autoAlpha: 1, y: 0, duration: 0.25 }, 1.25)
            .to(state, { p: reach[1], ects: total, duration: 1, onUpdate: place }, 1.1)
            .to(arcMaster, { strokeDasharray: `${100 - share} 100`, duration: 1 }, 1.1)
            // Le Doctorat : la marche en pointillés, sans chiffre.
            .to(steps[1], { autoAlpha: 0, y: -30, duration: 0.25 }, 2.15)
            .fromTo(steps[2], { autoAlpha: 0, y: 30 }, { autoAlpha: 1, y: 0, duration: 0.25 }, 2.35)
            .to(state, { p: reach[2], duration: 0.9, onUpdate: place }, 2.2)
            .to(next, { drawSVG: '100%', duration: 0.6 }, 2.9);

        return () => {
            root.classList.remove('is-pinned');
            gsap.set([...steps, arcLicence, arcMaster, next], { clearProps: 'all' });
            gsap.set(stair, { drawSVG: '100%' });
            marker.setAttribute('cx', 560);
            marker.setAttribute('cy', 210);
            if (count) count.textContent = String(total);
        };
    });

    // Petit écran : les grades montent un à un, l'escalier se dessine quand il apparaît.
    mm.add('(max-width: 1023px)', () => {
        steps.forEach((step) => gsap.from(step, { y: 40, opacity: 0, duration: 0.9, ease: 'power3.out', scrollTrigger: { trigger: step, start: 'top 85%', once: true } }));
        const art = $('[data-lmd-art]', root);
        const draw = gsap.timeline({ scrollTrigger: { trigger: art, start: 'top 80%', once: true } });
        gsap.set(next, { drawSVG: '0%' });
        gsap.set([arcLicence, arcMaster], { strokeDasharray: '0 100' });
        draw.to(state, { p: 1, ects: total, duration: 2.4, ease: 'power2.inOut', onUpdate: place }, 0)
            .to(arcLicence, { strokeDasharray: `${share} 100`, duration: 1.2, ease: 'power2.out' }, 0)
            .to(arcMaster, { strokeDasharray: `${100 - share} 100`, duration: 1.2, ease: 'power2.out' }, 1.1)
            .to(next, { drawSVG: '100%', duration: 0.6 }, 2.2);

        return () => gsap.set([arcLicence, arcMaster, next], { clearProps: 'all' });
    });
}

/* ───────── 3. Les mentions ───────── */
function mentions() {
    $$('[data-lmd-mentions]').forEach((section) => {
        const cards = $$('[data-lmd-mention]', section);
        ScrollTrigger.batch(cards, {
            start: 'top 90%',
            once: true,
            onEnter: (batch) => gsap.from(batch, { y: 50, opacity: 0, duration: 0.9, stagger: 0.1, ease: 'power3.out', clearProps: 'transform,opacity' }),
        });
    });
}

/* ───────── 4. M1 puis M2 ───────── */
function stages() {
    const root = $('[data-lmd-stages]');
    if (!root) return;

    const items = $$('[data-lmd-stage-item]', root);
    const tl = gsap.timeline({ scrollTrigger: { trigger: root, start: 'top 85%', once: true } });
    tl.from(items[0], { scale: 0, duration: 0.7, ease: 'back.out(2)' });
    items.slice(1).forEach((item) => {
        // Le trait en pointillés se trace, puis le cercle de l'année suivante apparaît.
        tl.fromTo(item, { '--line': 0 }, { '--line': 1, duration: 0.6, ease: 'power2.inOut' }).from(item, { scale: 0, duration: 0.7, ease: 'back.out(2)' }, '>-0.1');
    });
}

/* ───────── 5. Le Doctorat ───────── */
function doctorate() {
    const root = $('[data-lmd-doc]');
    const text = $('[data-lmd-doc-text]', root ?? document);
    if (!root || !text) return;

    SplitText.create(text, {
        type: 'words',
        wordsClass: 'word',
        autoSplit: true,
        onSplit: (self) =>
            gsap.from(self.words, {
                xPercent: (i) => (i % 2 ? 40 : -40),
                opacity: 0,
                duration: 1,
                stagger: 0.06,
                ease: 'power3.out',
                scrollTrigger: { trigger: root, start: 'top 70%', once: true },
            }),
    });
}
