// Page « Centre de recherche » : sept scènes (voir resources/views/pages/research.blade.php).
//
//  1. L'observatoire : un réseau de points se tisse (liens tracés, points allumés), suit le pointeur et s'éloigne au défilement ; le titre se lève lettre à lettre.
//  2. La phrase      : écran épinglé, les mots de la phrase s'allument un à un pendant que le fil se tend (DrawSVG).
//  3. Les personnes  : les fiches montent tour à tour, la photo respire, la carte s'incline au survol (souris).
//  4. Le domaine     : la ligne se trace, chaque mention est un point qui s'allume.
//  5. Le Master      : le réseau du fond dérive doucement avec le défilement.
//  6. L'invitation   : les mots de la phrase arrivent tour à tour de gauche et de droite.
//
// Renforcement progressif : sans JavaScript ou avec « réduire les animations », la page reste complète (voir resources/css/research.css).
import { DrawSVGPlugin } from 'gsap/DrawSVGPlugin';
import { gsap, ScrollTrigger, SplitText, MOTION_OK } from './gsap.js';

gsap.registerPlugin(DrawSVGPlugin);

const headerHeight = () => document.querySelector('.site-header')?.offsetHeight ?? 0;
const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

export function initResearch() {
    const mm = gsap.matchMedia();

    mm.add(MOTION_OK, (context) => {
        const run = () => {
            document.documentElement.classList.add('res-ready');
            hero();
            statement();
            people();
            domain();
            master();
            invite();
            ScrollTrigger.refresh();
        };

        // Les polices doivent être chargées avant de découper le texte en lettres et en mots.
        if (document.fonts?.status === 'loaded') run();
        else document.fonts.ready.then(() => context.add(run));
    });
}

/* ───────── 1. L'observatoire ───────── */
function hero() {
    const root = $('[data-res-hero]');
    if (!root) return;

    SplitText.create($('[data-res-title]', root), {
        type: 'words,chars',
        wordsClass: 'word',
        charsClass: 'char',
        autoSplit: true,
        onSplit: (self) => gsap.from(self.chars, { yPercent: 110, opacity: 0, duration: 1, stagger: 0.035, ease: 'power4.out' }),
    });
    gsap.from($$('[data-res-fade]', root), { autoAlpha: 0, y: 22, duration: 1, delay: 0.7, stagger: 0.12, ease: 'power3.out' });

    // Le réseau se tisse : les liens se tracent un à un, les points s'allument à leur arrivée.
    const edges = $$('[data-res-edge]', root);
    const nodes = $$('[data-res-node]', root);
    gsap.fromTo(edges, { drawSVG: '0%' }, { drawSVG: '100%', duration: 1.4, stagger: { each: 0.045, from: 'random' }, ease: 'power2.inOut', delay: 0.2 });
    gsap.from(nodes, { scale: 0, autoAlpha: 0, duration: 0.8, stagger: { each: 0.05, from: 'random' }, ease: 'back.out(2.4)', delay: 0.4 });
    // Quelques points respirent en continu.
    gsap.to(gsap.utils.shuffle(nodes).slice(0, 7), { scale: 1.7, duration: 1.8, ease: 'sine.inOut', repeat: -1, yoyo: true, stagger: { each: 0.4, from: 'random' }, delay: 1.6 });

    // L'anneau du sigle tourne en continu.
    const ring = $('[data-res-ring]', root);
    if (ring) gsap.to(ring, { rotation: 360, duration: 18, ease: 'none', repeat: -1, transformOrigin: '50% 50%' });

    // Parallaxe à la souris : deux plans qui bougent d'une quantité différente.
    const far = $('[data-res-layer="far"]', root);
    const near = $('[data-res-layer="near"]', root);
    if (far && near && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        const moves = [
            [gsap.quickTo(far, 'x', { duration: 0.9, ease: 'power3.out' }), gsap.quickTo(far, 'y', { duration: 0.9, ease: 'power3.out' }), 10],
            [gsap.quickTo(near, 'x', { duration: 0.9, ease: 'power3.out' }), gsap.quickTo(near, 'y', { duration: 0.9, ease: 'power3.out' }), 26],
        ];
        root.addEventListener('pointermove', (event) => {
            const rect = root.getBoundingClientRect();
            const dx = (event.clientX - rect.left) / rect.width - 0.5;
            const dy = (event.clientY - rect.top) / rect.height - 0.5;
            moves.forEach(([qx, qy, amount]) => {
                qx(-dx * amount);
                qy(-dy * amount);
            });
        });
    }

    // Au défilement, le réseau s'éloigne et s'estompe.
    gsap.to($('[data-res-net]', root), { scale: 1.18, opacity: 0.2, ease: 'none', scrollTrigger: { trigger: root, start: 'top top', end: 'bottom top', scrub: true } });
}

/* ───────── 2. La phrase ───────── */
function statement() {
    const root = $('[data-res-statement]');
    const quote = $('[data-res-quote]', root ?? document);
    const thread = $('[data-res-thread]', root ?? document);
    if (!root || !quote) return;

    const mm = gsap.matchMedia();
    // Écran épinglé sur grand écran ; sur petit écran, la même révélation suit simplement le défilement.
    const build = (pinned) => {
        const split = SplitText.create(quote, { type: 'words', wordsClass: 'word' });
        gsap.set(split.words, { opacity: 0.16 });
        if (thread) gsap.set(thread, { drawSVG: '0%' });

        const tl = gsap.timeline({
            defaults: { ease: 'none' },
            scrollTrigger: {
                trigger: root,
                start: pinned ? () => `top top+=${headerHeight()}` : 'top 70%',
                end: pinned ? () => `+=${Math.round(window.innerHeight * 1.6)}` : 'bottom 60%',
                pin: pinned,
                scrub: 0.6,
                anticipatePin: 1,
                invalidateOnRefresh: true,
            },
        });
        tl.to(split.words, { opacity: 1, stagger: 0.12, duration: 0.5 });
        if (thread) tl.to(thread, { drawSVG: '100%', duration: split.words.length * 0.12 }, 0.2);

        return () => split.revert();
    };

    mm.add('(min-width: 1024px)', () => build(true));
    mm.add('(max-width: 1023.98px)', () => build(false));
}

/* ───────── 3. Les personnes ───────── */
function people() {
    const cards = $$('[data-res-person]');
    if (!cards.length) return;

    gsap.set(cards, { autoAlpha: 0, yPercent: 14 });
    ScrollTrigger.batch(cards, {
        start: 'top 88%',
        once: true,
        onEnter: (batch) => gsap.to(batch, { autoAlpha: 1, yPercent: 0, duration: 1, stagger: 0.18, ease: 'power3.out' }),
    });

    // La photo respire quand la carte traverse l'écran.
    cards.forEach((card) => {
        const img = $('[data-res-photo] img', card);
        if (img) gsap.fromTo(img, { scale: 1.12, yPercent: -4 }, { scale: 1, yPercent: 4, ease: 'none', scrollTrigger: { trigger: card, start: 'top bottom', end: 'bottom top', scrub: true } });
    });

    // Petite inclinaison au survol (souris seulement).
    if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        cards.forEach((card) => {
            const rx = gsap.quickTo(card, 'rotationX', { duration: 0.5, ease: 'power3.out' });
            const ry = gsap.quickTo(card, 'rotationY', { duration: 0.5, ease: 'power3.out' });
            gsap.set(card, { transformPerspective: 900 });
            card.addEventListener('pointermove', (event) => {
                const rect = card.getBoundingClientRect();
                ry(((event.clientX - rect.left) / rect.width - 0.5) * 8);
                rx(-((event.clientY - rect.top) / rect.height - 0.5) * 8);
            });
            card.addEventListener('pointerleave', () => {
                rx(0);
                ry(0);
            });
        });
    }
}

/* ───────── 4. Le domaine ───────── */
function domain() {
    const chain = $('[data-res-chain]');
    if (!chain) return;

    const items = $$('[data-res-node-item]', chain);
    const dots = items.map((item) => $('.res-chain__dot', item));
    const labels = items.map((item) => $('.res-chain__link span:last-child', item));

    gsap.set(chain, { '--res-line': 0 });
    gsap.set(dots, { scale: 0 });
    gsap.set(labels, { autoAlpha: 0, y: 14 });

    const tl = gsap.timeline({ scrollTrigger: { trigger: chain, start: 'top 82%', once: true } });
    // La ligne (pseudo-élément) se trace par une échelle pilotée par la variable CSS --res-line.
    tl.to(chain, { '--res-line': 1, duration: 1.1, ease: 'power2.inOut' });
    tl.to(dots, { scale: 1, duration: 0.5, stagger: 0.18, ease: 'back.out(3)' }, 0.1);
    tl.to(labels, { autoAlpha: 1, y: 0, duration: 0.7, stagger: 0.18, ease: 'power3.out' }, 0.2);
}

/* ───────── 5. Le Master ───────── */
function master() {
    const root = $('[data-res-master]');
    const net = $('[data-res-master-net]', root ?? document);
    if (!root || !net) return;

    gsap.fromTo(net, { yPercent: -8, scale: 1.05 }, { yPercent: 8, scale: 1.2, ease: 'none', scrollTrigger: { trigger: root, start: 'top bottom', end: 'bottom top', scrub: true } });
}

/* ───────── 6. L'invitation ───────── */
function invite() {
    const root = $('[data-res-invite]');
    const text = $('[data-res-invite-text]', root ?? document);
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
                stagger: 0.12,
                ease: 'power3.out',
                scrollTrigger: { trigger: root, start: 'top 70%', once: true },
            });
        },
    });
}
