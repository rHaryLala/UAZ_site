// Page « Soutenir l'UAZ » : cinq scènes (voir resources/views/pages/support.blade.php).
//
//  1. Le chantier       : la photo respire et s'éloigne au défilement, le titre se lève mot à mot.
//  2. La première pierre : écran épinglé, les mots de la phrase s'allument un à un pendant que le mur monte brique après brique.
//  3. Les plans         : chaque planche monte à son arrivée, son budget se compte, le plan se dessine (projet sans photo).
//  4. Les montants      : les barres, proportionnelles aux budgets publiés, se déploient (aucune somme réunie n'est affichée).
//  5. L'invitation      : les mots de la phrase arrivent tour à tour de gauche et de droite.
//
// Renforcement progressif : sans JavaScript ou avec « réduire les animations », la page reste complète (voir resources/css/support.css).
import { DrawSVGPlugin } from 'gsap/DrawSVGPlugin';
import { gsap, ScrollTrigger, SplitText, MOTION_OK } from './gsap.js';

gsap.registerPlugin(DrawSVGPlugin);

const headerHeight = () => document.querySelector('.site-header')?.offsetHeight ?? 0;
const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

export function initSupport() {
    const mm = gsap.matchMedia();

    mm.add(MOTION_OK, (context) => {
        const run = () => {
            document.documentElement.classList.add('sup-ready');
            hero();
            statement();
            sheets();
            bars();
            invite();
            ScrollTrigger.refresh();
        };

        // Les polices doivent être chargées avant de découper le texte en mots.
        if (document.fonts?.status === 'loaded') run();
        else document.fonts.ready.then(() => context.add(run));
    });
}

/* ───────── 1. Le chantier ───────── */
function hero() {
    const root = $('[data-sup-hero]');
    if (!root) return;

    SplitText.create($('[data-sup-title]', root), {
        type: 'words,chars',
        wordsClass: 'word',
        charsClass: 'char',
        autoSplit: true,
        onSplit: (self) => gsap.from(self.chars, { yPercent: 110, opacity: 0, duration: 1, stagger: 0.03, ease: 'power4.out' }),
    });
    gsap.from($$('[data-sup-fade]', root), { autoAlpha: 0, y: 22, duration: 1, delay: 0.6, stagger: 0.12, ease: 'power3.out' });

    // La photo se rapproche en douceur à l'arrivée, puis s'éloigne quand on descend.
    const photo = $('[data-sup-photo]', root);
    if (photo) {
        gsap.from(photo, { scale: 1.12, duration: 2.2, ease: 'power2.out' });
        gsap.to(photo, { yPercent: 10, scale: 1.08, ease: 'none', scrollTrigger: { trigger: root, start: 'top top', end: 'bottom top', scrub: true } });
    }
}

/* ───────── 2. La première pierre ───────── */
function statement() {
    const root = $('[data-sup-statement]');
    const quote = $('[data-sup-quote]', root ?? document);
    const bricks = $$('[data-sup-brick]', root ?? document);
    if (!root || !quote) return;

    // Les briques montent rangée par rangée (du bas vers le haut), de gauche à droite dans une rangée.
    const ordered = [...bricks].sort((a, b) => Number(a.dataset.row) - Number(b.dataset.row) || Number(a.dataset.col) - Number(b.dataset.col));

    const mm = gsap.matchMedia();
    // Écran épinglé sur grand écran ; sur petit écran, la même révélation suit simplement le défilement.
    const build = (pinned) => {
        const split = SplitText.create(quote, { type: 'words', wordsClass: 'word' });
        gsap.set(split.words, { opacity: 0.16 });
        gsap.set(ordered, { autoAlpha: 0, scaleY: 0 });

        const tl = gsap.timeline({
            defaults: { ease: 'none' },
            scrollTrigger: {
                trigger: root,
                start: pinned ? () => `top top+=${headerHeight()}` : 'top 70%',
                end: pinned ? () => `+=${Math.round(window.innerHeight * 1.8)}` : 'bottom 55%',
                pin: pinned,
                scrub: 0.6,
                anticipatePin: 1,
                invalidateOnRefresh: true,
            },
        });
        tl.to(split.words, { opacity: 1, stagger: 0.1, duration: 0.5 });
        tl.to(ordered, { autoAlpha: 1, scaleY: 1, stagger: 0.04, duration: 0.4 }, 0.2);

        return () => split.revert();
    };

    mm.add('(min-width: 1024px)', () => build(true));
    mm.add('(max-width: 1023.98px)', () => build(false));
}

/* ───────── 3. Les plans ───────── */
const NUMBER = /\d(?:[\d   .,]*\d)?/;

/** Prépare le compte jusqu'au nombre du budget (« 275 000 € » ou « €275,000 »), dans la même écriture que le texte d'origine. */
function counter(el) {
    const original = el.textContent;
    const found = original.match(NUMBER);
    if (!found) return null;

    const end = Number(found[0].replace(/\D/g, ''));
    if (!Number.isFinite(end) || end <= 0) return null;
    const english = /\d,\d{3}/.test(found[0]);
    const write = (value) => {
        const n = Math.round(value);
        const text = english ? n.toLocaleString('en-US') : n.toLocaleString('fr-FR').replace(/[   ]/g, ' ');
        el.textContent = original.replace(found[0], text);
    };
    const state = { value: 0 };
    write(0);

    return { state, end, write, restore: () => (el.textContent = original) };
}

function sheets() {
    const items = $$('[data-sup-sheet]');
    if (!items.length) return;

    items.forEach((sheet) => {
        const media = $('.sup-sheet__media', sheet);
        const body = $('.sup-sheet__body', sheet);
        const plan = $('[data-sup-plan]', sheet);
        const paths = plan ? $$('path', plan) : [];
        const amount = $('[data-sup-amount]', sheet);
        const count = amount ? counter(amount) : null;

        gsap.set(sheet, { autoAlpha: 0, yPercent: 6 });
        if (paths.length) gsap.set(paths, { drawSVG: '0%' });

        const tl = gsap.timeline({ paused: true });
        tl.to(sheet, { autoAlpha: 1, yPercent: 0, duration: 0.9, ease: 'power3.out' });
        if (paths.length) tl.to(paths, { drawSVG: '100%', duration: 1.4, stagger: 0.25, ease: 'power2.inOut' }, 0.2);
        if (count) tl.to(count.state, { value: count.end, duration: 2, ease: 'power3.out', onUpdate: () => count.write(count.state.value), onComplete: count.restore }, 0.35);
        ScrollTrigger.create({ trigger: sheet, start: 'top 82%', once: true, onEnter: () => tl.play() });

        // La photo (ou le plan) glisse un peu moins vite que la page.
        const img = $('picture img, img', media);
        if (img) gsap.fromTo(img, { yPercent: -5, scale: 1.1 }, { yPercent: 5, scale: 1.1, ease: 'none', scrollTrigger: { trigger: sheet, start: 'top bottom', end: 'bottom top', scrub: true } });
        if (body) gsap.from($$('.sup-sheet__num', body), { yPercent: 30, autoAlpha: 0, duration: 1.2, ease: 'power3.out', scrollTrigger: { trigger: sheet, start: 'top 80%', once: true } });
    });
}

/* ───────── 4. Les montants ───────── */
function bars() {
    const root = $('[data-sup-bars]');
    if (!root) return;

    const fills = $$('[data-sup-fill]', root);
    gsap.set(fills, { scaleX: 0 });
    gsap.to(fills, { scaleX: 1, duration: 1.4, stagger: 0.2, ease: 'power3.out', scrollTrigger: { trigger: root, start: 'top 85%', once: true } });
}

/* ───────── 5. L'invitation ───────── */
function invite() {
    const root = $('[data-sup-invite]');
    const text = $('[data-sup-invite-text]', root ?? document);
    if (!root || !text) return;

    SplitText.create(text, {
        type: 'words',
        wordsClass: 'word',
        autoSplit: true,
        onSplit: (self) => {
            gsap.from(self.words, {
                xPercent: (i) => (i % 2 ? 60 : -60),
                opacity: 0,
                duration: 1,
                stagger: 0.1,
                ease: 'power3.out',
                scrollTrigger: { trigger: root, start: 'top 70%', once: true },
            });
        },
    });
}
