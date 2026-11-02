// Galerie photo : liste des albums et page d'un album (voir resources/views/albums/index.blade.php et show.blade.php).
//
//  1. Le vernissage  : un mur de photos incliné dérive en sens contraires (couvertures des albums) ; le titre se lève lettre à lettre,
//                      les nombres d'albums et de photos se comptent. Page d'un album : la couverture respire et glisse au défilement.
//  2. L'exposition   : chaque cadre se dévoile par un rideau (clip-path), pivote en arrivant, sa photo glisse dans son passe-partout (parallaxe) ;
//                      un projecteur suit le pointeur, un curseur « Ouvrir » accompagne les cadres, le mur s'incline avec la vitesse du défilement.
//                      Le filtre par année réorganise le mur avec Flip.
//  3. La suite       : les mots de la phrase arrivent tour à tour.
//
// Renforcement progressif : sans JavaScript ou avec « réduire les animations », la page reste complète (voir resources/css/gallery.css) ;
// le filtre par année, lui, fonctionne aussi avec « réduire les animations » (sans mouvement).
import { Flip } from 'gsap/Flip';
import { gsap, ScrollTrigger, SplitText, MOTION_OK, NO_MOTION } from './gsap.js';

gsap.registerPlugin(Flip);

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

export function initGallery() {
    const mm = gsap.matchMedia();

    mm.add(MOTION_OK, (context) => {
        const run = () => {
            document.documentElement.classList.add('gal-ready');
            hero();
            expo();
            filters(true);
            more();
            ScrollTrigger.refresh();
        };

        // Les polices doivent être chargées avant de découper le texte en lettres et en mots.
        if (document.fonts?.status === 'loaded') run();
        else document.fonts.ready.then(() => context.add(run));
    });

    // Mouvement réduit : pas d'animation, mais le filtre par année reste utilisable.
    mm.add(NO_MOTION, () => {
        filters(false);
    });
}

/* ───────── 1. Le vernissage ───────── */
function hero() {
    const root = $('[data-gal-hero]');
    if (!root) return;

    SplitText.create($('[data-gal-title]', root), {
        type: 'words,chars',
        wordsClass: 'word',
        charsClass: 'char',
        autoSplit: true,
        onSplit: (self) => gsap.from(self.chars, { yPercent: 110, opacity: 0, duration: 1, stagger: 0.03, ease: 'power4.out' }),
    });
    gsap.from($$('[data-gal-fade]', root), { autoAlpha: 0, y: 22, duration: 1, delay: 0.6, stagger: 0.12, ease: 'power3.out' });

    // Les nombres se comptent, puis le texte d'origine est rétabli.
    $$('[data-gal-count]', root).forEach((el) => {
        const end = Number(el.dataset.galCount);
        const original = el.textContent;
        const state = { value: 0 };
        el.textContent = '0';
        gsap.to(state, {
            value: end,
            duration: 1.8,
            delay: 0.9,
            ease: 'power3.out',
            onUpdate: () => (el.textContent = String(Math.round(state.value))),
            onComplete: () => (el.textContent = original),
        });
    });

    // Le mur de couvertures dérive : colonnes qui montent et qui descendent, à des vitesses différentes ; il s'arrête hors écran.
    const drifts = $$('[data-gal-col]', root).map((col, i) => {
        const duration = 55 + i * 9;

        return col.dataset.galCol === 'down'
            ? gsap.fromTo(col, { yPercent: -50 }, { yPercent: 0, duration, ease: 'none', repeat: -1 })
            : gsap.to(col, { yPercent: -50, duration, ease: 'none', repeat: -1 });
    });
    if (drifts.length) {
        ScrollTrigger.create({
            trigger: root,
            start: 'top bottom',
            end: 'bottom top',
            onToggle: (self) => drifts.forEach((tween) => (self.isActive ? tween.resume() : tween.pause())),
        });
        gsap.from($('.gal-hero__wall', root), { opacity: 0, scale: 1.12, duration: 2, ease: 'power2.out' });
        gsap.to($('.gal-hero__wall', root), { yPercent: 10, ease: 'none', scrollTrigger: { trigger: root, start: 'top top', end: 'bottom top', scrub: true } });
    }

    // Page d'un album : la couverture se pose, puis glisse plus lentement que la page.
    const cover = $('[data-gal-cover]', root);
    if (cover) {
        gsap.from(cover, { scale: 1.2, duration: 2.4, ease: 'power3.out' });
        gsap.to(cover, { yPercent: 14, scale: 1.08, ease: 'none', scrollTrigger: { trigger: root, start: 'top top', end: 'bottom top', scrub: true } });
    }
}

/* ───────── 2. L'exposition ───────── */
function expo() {
    const root = $('[data-gal-expo]');
    if (!root) return;

    frames(root);
    spotlight(root);
}

function frames(root) {
    const items = $$('[data-gal-frame]', root);
    const mats = items.map((item) => $('[data-gal-mat]', item)).filter(Boolean);

    items.forEach((item, i) => {
        const mat = $('[data-gal-mat]', item);
        const art = $('.gal-frame__art', item);
        const label = $('.gal-frame__label, .gal-frame__caption', item);
        const img = $('[data-gal-img]', item);
        const once = { trigger: item, start: 'top 88%', once: true };

        // Le cadre se dévoile par un rideau qui se lève, en pivotant légèrement vers sa place.
        if (mat) {
            gsap.fromTo(mat, { clipPath: 'inset(100% 0% 0% 0%)' }, { clipPath: 'inset(0% 0% 0% 0%)', duration: 1.3, ease: 'power4.inOut', scrollTrigger: once, onComplete: () => gsap.set(mat, { clearProps: 'clipPath' }) });
        }
        if (art) gsap.from(art, { rotation: i % 2 ? 2.2 : -2.2, y: 70, duration: 1.5, ease: 'power3.out', scrollTrigger: once });
        if (label) gsap.from(label, { autoAlpha: 0, y: 24, duration: 0.9, delay: 0.5, ease: 'power3.out', scrollTrigger: once });

        // La photo glisse dans son passe-partout.
        if (img) gsap.fromTo(img, { yPercent: -9, scale: 1.22 }, { yPercent: 9, scale: 1.22, ease: 'none', scrollTrigger: { trigger: item, start: 'top bottom', end: 'bottom top', scrub: true } });
    });

    // Le mur s'incline avec la vitesse du défilement, puis se redresse.
    if (mats.length) {
        ScrollTrigger.create({
            trigger: root,
            start: 'top bottom',
            end: 'bottom top',
            onUpdate: (self) => gsap.to(mats, { skewY: gsap.utils.clamp(-3, 3, self.getVelocity() / -700), duration: 0.5, ease: 'power3.out', overwrite: 'auto' }),
        });
        ScrollTrigger.addEventListener('scrollEnd', () => gsap.to(mats, { skewY: 0, duration: 0.8, ease: 'power3.out', overwrite: 'auto' }));
    }
}

/** Projecteur et curseur : uniquement avec une vraie souris. */
function spotlight(root) {
    if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;

    const cursor = $('[data-gal-cursor]', root);
    const spotX = gsap.quickTo(root, '--mx', { duration: 0.6, ease: 'power3.out' });
    const spotY = gsap.quickTo(root, '--my', { duration: 0.6, ease: 'power3.out' });
    const cursorX = cursor ? gsap.quickTo(cursor, 'x', { duration: 0.35, ease: 'power3.out' }) : null;
    const cursorY = cursor ? gsap.quickTo(cursor, 'y', { duration: 0.35, ease: 'power3.out' }) : null;
    if (cursor) gsap.set(cursor, { scale: 0 });

    root.addEventListener('pointermove', (event) => {
        const rect = root.getBoundingClientRect();
        if (!root.classList.contains('is-spot')) {
            // Premier mouvement : le projecteur apparaît là où est le pointeur, sans traverser le mur.
            gsap.set(root, { '--mx': event.clientX - rect.left, '--my': event.clientY - rect.top });
            root.classList.add('is-spot');
        }
        spotX(event.clientX - rect.left);
        spotY(event.clientY - rect.top);
        cursorX?.(event.clientX);
        cursorY?.(event.clientY);
    });
    root.addEventListener('pointerleave', () => {
        root.classList.remove('is-spot');
        if (cursor) gsap.to(cursor, { scale: 0, opacity: 0, duration: 0.25 });
    });

    // Le curseur apparaît au-dessus d'un cadre.
    if (cursor) {
        root.addEventListener('pointerover', (event) => {
            if (event.target.closest('.gal-frame__art')) gsap.to(cursor, { scale: 1, opacity: 1, duration: 0.35, ease: 'back.out(2)', overwrite: 'auto' });
        });
        root.addEventListener('pointerout', (event) => {
            if (event.target.closest('.gal-frame__art') && !event.relatedTarget?.closest?.('.gal-frame__art')) gsap.to(cursor, { scale: 0, opacity: 0, duration: 0.25, overwrite: 'auto' });
        });
    }
}

/* ───────── Filtre par année (Flip) ───────── */
function filters(animated) {
    const bar = $('[data-gal-filter]');
    const wall = $('[data-gal-wall]');
    if (!bar || !wall) return;

    const items = $$('[data-gal-frame]', wall);
    const buttons = $$('[data-gal-year]', bar);
    const status = $('[data-gal-status]');
    bar.hidden = false;

    const apply = (year) => {
        let slot = 0;
        items.forEach((item) => {
            item.hidden = year !== 'all' && item.dataset.year !== year;
            // Le rythme d'accrochage suit les cadres visibles, pas leur place dans la liste.
            if (!item.hidden) item.dataset.slot = String(slot++ % 4);
        });
        buttons.forEach((button) => {
            const on = button.dataset.galYear === year;
            button.classList.toggle('is-active', on);
            button.setAttribute('aria-pressed', String(on));
        });
        if (status) status.textContent = (slot === 1 ? status.dataset.one : status.dataset.other).replace(':count', String(slot));
    };

    buttons.forEach((button) =>
        button.addEventListener('click', () => {
            const year = button.dataset.galYear;
            if (!animated) return apply(year);

            const state = Flip.getState(items);
            apply(year);
            Flip.from(state, {
                duration: 0.9,
                ease: 'power3.inOut',
                absolute: true,
                stagger: 0.03,
                onEnter: (els) => gsap.fromTo(els, { opacity: 0, scale: 0.85 }, { opacity: 1, scale: 1, duration: 0.7, ease: 'power3.out' }),
                onLeave: (els) => gsap.to(els, { opacity: 0, scale: 0.85, duration: 0.45, ease: 'power2.in' }),
                onComplete: () => ScrollTrigger.refresh(),
            });
        }),
    );
}

/* ───────── 3. La suite ───────── */
function more() {
    const root = $('[data-gal-more]');
    const text = $('[data-gal-more-text]', root ?? document);
    if (!root || !text) return;

    SplitText.create(text, {
        type: 'words',
        wordsClass: 'word',
        autoSplit: true,
        onSplit: (self) =>
            gsap.from(self.words, {
                xPercent: (i) => (i % 2 ? 60 : -60),
                opacity: 0,
                duration: 1,
                stagger: 0.08,
                ease: 'power3.out',
                scrollTrigger: { trigger: root, start: 'top 70%', once: true },
            }),
    });
}
