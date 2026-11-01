// Page « Dortoirs » : une journée qui passe, de l'aube à la nuit (voir resources/views/pages/dormitories.blade.php).
//
//  1. La porte      : « Dortoirs » pousse lettre à lettre ; l'arche sur l'allée du campus s'ouvre en défilant jusqu'au plein écran (pin + clip-path).
//  2. Le cœur       : la phrase d'accueil se révèle ligne à ligne.
//  3. Résidences    : les chiffres montent (80, 68) ; les deux cartes se croisent en défilant ; la photo suit la souris.
//  3b. Le mur       : les photos des chambres tombent de travers, puis se déplacent et se lancent à la main (Draggable + Inertia, souris).
//  4. Projet R+2    : le schéma du bâtiment se construit depuis le centre, ses fenêtres s'allument, les étages prévus se tracent (DrawSVG).
//  5. La nuit tombe : les fenêtres de la silhouette du campus s'allument une à une, puis vacillent doucement.
//
// Renforcement progressif : sans JavaScript ou avec « réduire les animations », la page reste complète (voir resources/css/dormitories.css).
import { DrawSVGPlugin } from 'gsap/DrawSVGPlugin';
import { Draggable } from 'gsap/Draggable';
import { InertiaPlugin } from 'gsap/InertiaPlugin';
import { gsap, ScrollTrigger, SplitText, MOTION_OK } from './gsap.js';

gsap.registerPlugin(DrawSVGPlugin, Draggable, InertiaPlugin);

const DESKTOP = '(min-width: 1024px)';
const headerHeight = () => document.querySelector('.site-header')?.offsetHeight ?? 0;
const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

export function initDormitories() {
    const mm = gsap.matchMedia();

    mm.add(MOTION_OK, (context) => {
        const run = () => {
            document.documentElement.classList.add('dor-ready');
            hero();
            statement();
            houses();
            wall();
            plan();
            night();
            ScrollTrigger.refresh();
        };

        // Les polices doivent être chargées avant de découper le texte.
        if (document.fonts?.status === 'loaded') run();
        else document.fonts.ready.then(() => context.add(run));
    });

    // Mouvement réduit : pas d'animation, mais quelques fenêtres allumées pour l'ambiance.
    mm.add('(prefers-reduced-motion: reduce)', () => {
        $$('[data-dor-window]').forEach((w, i) => i % 3 === 0 && w.classList.add('is-lit'));
    });
}

/* ───────── 1. La porte ───────── */
function hero() {
    const root = $('[data-dor-hero]');
    if (!root) return;

    const stage = $('[data-dor-stage]', root);
    const door = $('[data-dor-door]', root);
    const photo = $('img', door ?? root);
    const title = $('[data-dor-title]', root);
    const lead = $('[data-dor-fade]', root);
    const sun = $('[data-dor-sun]', root);
    const crumbs = $('nav', $('[data-dor-text]', root));

    // Entrée : les lettres poussent du sol, comme des bâtiments ; le reste suit.
    SplitText.create(title, {
        type: 'chars',
        charsClass: 'char',
        autoSplit: true,
        onSplit: (self) => gsap.from(self.chars, { scaleY: 0, yPercent: 40, transformOrigin: '50% 100%', opacity: 0, duration: 0.9, stagger: 0.07, ease: 'back.out(2.2)' }),
    });
    gsap.from(lead, { autoAlpha: 0, y: 24, duration: 1, delay: 0.8, ease: 'power3.out' });
    gsap.fromTo(sun, { y: 60, autoAlpha: 0 }, { y: 0, autoAlpha: 1, duration: 2, ease: 'power2.out' });
    gsap.to(sun, { y: '-=10', duration: 3, ease: 'sine.inOut', yoyo: true, repeat: -1 });

    if (!door) return;
    root.classList.add('is-live');

    const closed = getComputedStyle(door).clipPath;
    const tl = gsap.timeline({
        defaults: { ease: 'none' },
        scrollTrigger: {
            trigger: stage,
            start: () => `top top+=${headerHeight()}`,
            end: () => `+=${Math.round(window.innerHeight * 1.7)}`,
            pin: true,
            scrub: 0.7,
            anticipatePin: 1,
            invalidateOnRefresh: true,
        },
    });

    // La porte s'ouvre : l'arche grandit jusqu'à remplir l'écran, la photo se recale, le titre passe en blanc.
    tl.fromTo(door, { clipPath: closed }, { clipPath: 'inset(0% 0% 0% 0% round 0px 0px 0px 0px)', ease: 'power2.inOut', duration: 1 }, 0);
    tl.fromTo(photo, { scale: 1.45 }, { scale: 1, duration: 1 }, 0);
    tl.to(title, { color: '#fff', textShadow: '0 0.04em 0.4em rgb(10 15 40 / 0.55)', duration: 0.5 }, 0.2);
    tl.to(lead, { color: '#ffd9a8', duration: 0.5 }, 0.2);
    if (crumbs) tl.to(crumbs, { autoAlpha: 0, duration: 0.3 }, 0);
    tl.to(sun, { scale: 1.6, autoAlpha: 0, y: -80, duration: 0.8 }, 0);
    tl.to({}, { duration: 0.3 });
}

/* ───────── 2. Le cœur de la vie étudiante ───────── */
function statement() {
    const text = $('[data-dor-statement-text]');
    if (!text) return;

    SplitText.create(text, {
        type: 'lines',
        mask: 'lines',
        autoSplit: true,
        onSplit: (self) =>
            gsap.from(self.lines, { yPercent: 110, duration: 1.2, stagger: 0.14, ease: 'expo.out', scrollTrigger: { trigger: text, start: 'top 85%', once: true } }),
    });
}

/* ───────── 3. Les deux résidences ───────── */
function houses() {
    const cards = $$('[data-dor-house]');
    if (!cards.length) return;
    const fine = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    cards.forEach((card) => {
        // Le chiffre monte de zéro à son arrivée.
        const counter = $('[data-dor-count]', card);
        if (counter) {
            const target = Number(counter.dataset.dorCount);
            counter.textContent = '0';
            gsap.to(counter, { textContent: target, duration: 2, ease: 'power2.out', snap: { textContent: 1 }, scrollTrigger: { trigger: card, start: 'top 75%', once: true } });
        }

        // La photo suit doucement la souris (écrans à souris).
        const img = $('[data-dor-house-photo] img', card);
        if (img && fine) {
            const moveX = gsap.quickTo(img, 'x', { duration: 0.8, ease: 'power3.out' });
            const moveY = gsap.quickTo(img, 'y', { duration: 0.8, ease: 'power3.out' });
            gsap.set(img, { scale: 1.08 });
            card.addEventListener('pointermove', (event) => {
                const box = card.getBoundingClientRect();
                moveX(((event.clientX - box.left) / box.width - 0.5) * -26);
                moveY(((event.clientY - box.top) / box.height - 0.5) * -26);
            });
            card.addEventListener('pointerleave', () => {
                moveX(0);
                moveY(0);
            });
        }

    });

    // Grand écran : les deux cartes se croisent en défilant.
    const mm = gsap.matchMedia();
    mm.add(DESKTOP, () => {
        cards.forEach((card, i) => {
            gsap.fromTo(card, { y: i === 0 ? 70 : -30 }, { y: i === 0 ? -50 : 60, ease: 'none', scrollTrigger: { trigger: '[data-dor-houses]', start: 'top bottom', end: 'bottom top', scrub: true } });
        });
    });
}

/* ───────── 3b. Le mur de photos ───────── */
function wall() {
    const root = $('[data-dor-wall]');
    const board = $('[data-dor-board]', root ?? document);
    const cards = $$('[data-dor-polaroid]', root ?? document);
    if (!root || !board || !cards.length) return;

    // Chaque photo est épinglée de travers ; à l'arrivée, elles tombent l'une après l'autre.
    cards.forEach((card) => gsap.set(card, { rotation: Number(card.dataset.tilt) || 0 }));
    gsap.from(cards, { y: 160, rotation: () => gsap.utils.random(-20, 20), autoAlpha: 0, duration: 1.2, stagger: 0.16, ease: 'back.out(1.4)', scrollTrigger: { trigger: board, start: 'top 80%', once: true } });

    // Souris et écran assez large seulement : sur écran tactile ou petit, glisser sur une photo empêcherait de défiler la page.
    if (!window.matchMedia('(min-width: 768px) and (hover: hover) and (pointer: fine)').matches) return;

    root.classList.add('is-live');
    const hint = $('[data-dor-hint]', root);
    if (hint) hint.hidden = false;

    Draggable.create(cards, {
        type: 'x,y',
        bounds: root,
        inertia: true,
        edgeResistance: 0.75,
        zIndexBoost: true,
        onPress() {
            this.target.classList.add('is-dragging');
            gsap.to(this.target, { scale: 1.06, rotation: 0, duration: 0.3, ease: 'power2.out' });
            if (hint) gsap.to(hint, { autoAlpha: 0, duration: 0.4 });
        },
        onRelease() {
            this.target.classList.remove('is-dragging');
            // La photo se repose, légèrement de travers, là où on l'a lâchée.
            gsap.to(this.target, { scale: 1, rotation: Number(this.target.dataset.tilt) || 0, duration: 0.8, ease: 'elastic.out(1, 0.55)' });
        },
    });
}

/* ───────── 4. Le projet R+2 ───────── */
function plan() {
    const root = $('[data-dor-plan]');
    const stage = $('[data-dor-plan-stage]', root ?? document);
    const cells = $$('[data-dor-cell]', root ?? document);
    if (!root || !stage || !cells.length) return;

    const futures = $$('[data-dor-future]', root);
    const outlines = futures.map((floor) => $('rect', floor));
    const labels = futures.map((floor) => $('span', floor));
    const vision = $$('[data-dor-vision]', root);

    const build = (tl) => {
        // Les fenêtres allumées apparaissent depuis le centre, comme si le bâtiment s'éclairait.
        tl.fromTo(cells, { autoAlpha: 0, y: 36, scale: 0.9, filter: 'brightness(0.4)' }, { autoAlpha: 1, y: 0, scale: 1, filter: 'brightness(1)', duration: 0.6, stagger: { each: 0.1, from: 'center' }, ease: 'back.out(1.6)' }, 0);
        // Les étages prévus se tracent au-dessus.
        tl.fromTo(outlines, { drawSVG: '0%' }, { drawSVG: '100%', duration: 0.9, stagger: 0.25, ease: 'power2.inOut' }, '>-0.1');
        tl.fromTo(labels, { autoAlpha: 0 }, { autoAlpha: 1, duration: 0.5, stagger: 0.25 }, '<0.4');
        tl.fromTo(vision, { autoAlpha: 0, y: 20 }, { autoAlpha: 1, y: 0, duration: 0.8, stagger: 0.15, ease: 'power3.out' }, '>-0.2');
    };

    // État de départ (avant que la scène démarre).
    gsap.set(cells, { autoAlpha: 0 });
    gsap.set(outlines, { drawSVG: '0%' });
    gsap.set(labels, { autoAlpha: 0 });
    gsap.set(vision, { autoAlpha: 0 });

    const mm = gsap.matchMedia();
    mm.add(`${DESKTOP} and (prefers-reduced-motion: no-preference)`, () => {
        root.classList.add('is-live');
        const tl = gsap.timeline({
            defaults: { ease: 'none' },
            scrollTrigger: { trigger: stage, start: () => `top top+=${headerHeight()}`, end: () => `+=${Math.round(window.innerHeight * 2)}`, pin: true, scrub: 0.6, anticipatePin: 1, invalidateOnRefresh: true },
        });
        build(tl);
        tl.to({}, { duration: 0.4 });

        return () => root.classList.remove('is-live');
    });

    // Petit écran : la construction se joue une fois, à l'arrivée du schéma.
    mm.add(`(max-width: 1023px) and (prefers-reduced-motion: no-preference)`, () => {
        const tl = gsap.timeline({ scrollTrigger: { trigger: '[data-dor-building]', start: 'top 80%', once: true } });
        build(tl);
    });
}

/* ───────── 5. La nuit tombe ───────── */
function night() {
    const root = $('[data-dor-night]');
    const windows = $$('[data-dor-window]', root ?? document);
    if (!root || !windows.length) return;

    ScrollTrigger.create({
        trigger: root,
        start: 'top 70%',
        once: true,
        onEnter: () => {
            // Les fenêtres s'allument dans le désordre…
            gsap.utils.shuffle([...windows]).forEach((windowEl, i) => {
                if (Math.random() < 0.72) gsap.delayedCall(i * 0.07 + Math.random() * 0.5, () => windowEl.classList.add('is-lit'));
            });
            // … puis l'une d'elles vacille de temps en temps, comme une vie qui continue.
            gsap.delayedCall(3.5, () => {
                const flicker = () => {
                    const target = gsap.utils.random(windows);
                    target.classList.toggle('is-lit');
                    gsap.delayedCall(gsap.utils.random(1.2, 3.2), flicker);
                };
                flicker();
            });
        },
    });
}
