// Pages « Admission Licence » et « Admission Master » (voir resources/views/pages/admissions.blade.php et config/admissions.php).
//
//  1. Ouverture : le titre se découpe en mots qui montent, le décompte avant la prochaine date s'incrémente, la photo se dévoile,
//                 le sceau de l'université tourne doucement en défilant.
//  2. Calendrier: écran épinglé ; un point suit le chemin des dates (MotionPath) qui se trace derrière lui (DrawSVG) ; chaque date s'éclaire à son passage.
//  3. Dossier   : la chemise s'ouvre en 3D, chaque pièce se coche quand on arrive à sa hauteur, un compteur monte, un tampon « Dossier complet » clôt.
//  4. Mentions  : une carte s'ouvre, les autres se réorganisent en douceur (Flip).
//  5. Dépôt     : l'affiche officielle s'incline en suivant la souris.
//
// Renforcement progressif : sans JavaScript ou avec « réduire les animations », la page reste complète (voir resources/css/admissions.css).
import { DrawSVGPlugin } from 'gsap/DrawSVGPlugin';
import { MotionPathPlugin } from 'gsap/MotionPathPlugin';
import { Flip } from 'gsap/Flip';
import { gsap, ScrollTrigger, SplitText, MOTION_OK } from './gsap.js';

gsap.registerPlugin(DrawSVGPlugin, MotionPathPlugin, Flip);

const DESKTOP_MOTION = '(min-width: 1024px) and (prefers-reduced-motion: no-preference)';
const headerHeight = () => document.querySelector('.site-header')?.offsetHeight ?? 0;
const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

export function initAdmissions() {
    // Les cartes de mentions fonctionnent aussi sans animation (changement instantané).
    mentions();

    const mm = gsap.matchMedia();

    mm.add(MOTION_OK, (context) => {
        const run = () => {
            document.documentElement.classList.add('adm-ready');
            hero();
            calendar();
            dossier();
            tilt();
            ScrollTrigger.refresh();
        };

        // Les polices doivent être chargées avant de découper le titre en mots.
        if (document.fonts?.status === 'loaded') run();
        else document.fonts.ready.then(() => context.add(run));
    });
}

/* ───────── 1. Ouverture ───────── */
function hero() {
    const root = $('[data-adm-hero]');
    if (!root) return;

    SplitText.create($('[data-adm-title]', root), {
        type: 'words',
        wordsClass: 'word',
        mask: 'words',
        autoSplit: true,
        onSplit: (self) => gsap.from(self.words, { yPercent: 115, duration: 1.2, stagger: 0.12, ease: 'expo.out', delay: 0.1 }),
    });
    gsap.from($$('[data-adm-fade]', root), { autoAlpha: 0, y: 24, duration: 0.9, stagger: 0.12, delay: 0.45, ease: 'power3.out' });

    // Le décompte monte de zéro à son nombre de jours.
    const count = $('[data-adm-count]', root);
    if (count) {
        const target = Number(count.dataset.admCount);
        count.textContent = '0';
        gsap.to(count, { textContent: target, duration: 1.8, delay: 0.9, ease: 'power2.out', snap: { textContent: 1 } });
    }

    // La photo se dévoile de haut en bas, puis glisse lentement en défilant.
    const photo = $('[data-adm-photo]', root);
    if (photo) {
        const img = $('img', photo);
        gsap.fromTo(photo, { clipPath: 'inset(0 0 100% 0)' }, { clipPath: 'inset(0 0 0% 0)', duration: 1.4, delay: 0.3, ease: 'expo.inOut' });
        gsap.fromTo(img, { scale: 1.25 }, { scale: 1, duration: 1.8, delay: 0.3, ease: 'expo.out' });
        gsap.to(img, { yPercent: 8, ease: 'none', scrollTrigger: { trigger: root, start: 'top top', end: 'bottom top', scrub: true } });
    }

    // Le sceau de l'université tourne doucement avec le défilement.
    const seal = $('[data-adm-seal]', root);
    if (seal) gsap.to(seal, { rotation: 110, ease: 'none', scrollTrigger: { trigger: root, start: 'top top', end: 'bottom top', scrub: true } });
}

/* ───────── 2. Calendrier ───────── */
function calendar() {
    const root = $('[data-adm-calendar]');
    const stage = $('[data-adm-cal-stage]', root ?? document);
    const path = $('[data-adm-path]', root ?? document);
    const dot = $('[data-adm-dot]', root ?? document);
    const nodes = $$('[data-adm-node]', root ?? document);
    const miles = $$('[data-adm-mile]', root ?? document);
    if (!root || !stage || !path || !dot || !miles.length) return;

    const along = (start, end) => ({ path, align: path, alignOrigin: [0.5, 0.5], start, end });
    const progressOf = (i) => (miles.length > 1 ? i / (miles.length - 1) : 1);

    const mm = gsap.matchMedia();

    mm.add(DESKTOP_MOTION, () => {
        // Si le contenu est plus haut que l'écran (rare), on ne l'épingle pas.
        const fits = stage.scrollHeight <= window.innerHeight - headerHeight() + 40;
        if (!fits) return fallback();

        root.classList.add('is-live');
        nodes.forEach((node, i) => gsap.set(node, { motionPath: along(progressOf(i), progressOf(i)) }));
        gsap.set(dot, { motionPath: along(0, 0) });
        gsap.set(path, { drawSVG: '0%' });
        gsap.set(miles, { opacity: 0.3, y: 34 });

        const tl = gsap.timeline({
            defaults: { ease: 'none' },
            scrollTrigger: {
                trigger: stage,
                start: () => `top top+=${headerHeight()}`,
                end: () => `+=${Math.round(window.innerHeight * 1.5)}`,
                pin: true,
                scrub: 0.6,
                anticipatePin: 1,
                invalidateOnRefresh: true,
            },
        });
        tl.to(path, { drawSVG: '100%', duration: 1 }, 0);
        tl.to(dot, { motionPath: along(0, 1), duration: 1 }, 0);
        miles.forEach((mile, i) => tl.to(mile, { opacity: 1, y: 0, duration: 0.22, ease: 'power2.out' }, Math.max(0, progressOf(i) - 0.1)));
        tl.to({}, { duration: 0.25 });

        return () => root.classList.remove('is-live');
    });

    // Petit écran, ou contenu trop haut : les dates apparaissent l'une après l'autre à leur arrivée.
    mm.add('(max-width: 1023px) and (prefers-reduced-motion: no-preference)', () => fallback());

    function fallback() {
        gsap.from(miles, { autoAlpha: 0, y: 40, duration: 0.9, stagger: 0.14, ease: 'expo.out', scrollTrigger: { trigger: miles[0], start: 'top 85%', once: true } });
        const days = $$('[data-adm-day]', root);
        if (days.length) gsap.from(days, { autoAlpha: 0, scale: 0.8, duration: 0.5, stagger: 0.07, ease: 'back.out(1.6)', scrollTrigger: { trigger: days[0], start: 'top 90%', once: true } });
    }
}

/* ───────── 3. Dossier ───────── */
function dossier() {
    const root = $('[data-adm-dossier]');
    const list = $('[data-adm-docs]', root ?? document);
    const docs = $$('[data-adm-doc]', root ?? document);
    if (!root || !list || !docs.length) return;

    const flap = $('[data-adm-flap]', root);
    const stamp = $('[data-adm-stamp]', root);
    const counter = $('[data-adm-docs-count]', root);
    const ticks = docs.map((doc) => $('[data-adm-tick]', doc));
    const scan = $('[data-adm-scan]', root);

    gsap.set(docs, { opacity: 0.32 });
    gsap.set(ticks, { drawSVG: '0%' });
    gsap.set(stamp, { autoAlpha: 0, scale: 2.2, rotation: -20 });
    if (scan) scan.style.setProperty('--adm-scan-h', '10rem');

    const n = docs.length;
    const counted = { value: 0 };
    const tl = gsap.timeline({
        defaults: { ease: 'none' },
        scrollTrigger: { trigger: list, start: 'top 72%', end: 'bottom 62%', scrub: 0.5 },
    });

    // La chemise s'ouvre comme un livre (pivot à gauche), puis chaque pièce se coche à son tour.
    if (flap) tl.to(flap, { rotateY: -158, transformOrigin: 'left center', duration: 0.1, ease: 'power2.inOut' }, 0);
    docs.forEach((doc, i) => {
        const at = 0.1 + (0.78 * i) / n;
        const slice = (0.78 / n) * 0.9;
        tl.to(doc, { opacity: 1, duration: slice }, at);
        tl.to(ticks[i], { drawSVG: '100%', duration: slice, ease: 'power2.out' }, at);
        tl.to(counted, { value: i + 1, duration: slice, snap: { value: 1 }, onUpdate: () => counter && (counter.textContent = String(Math.round(counted.value))) }, at);
    });
    if (stamp) tl.to(stamp, { autoAlpha: 1, scale: 1, rotation: -8, duration: 0.07, ease: 'back.out(2.2)' }, 0.92);
}

/* ───────── 4. Mentions ───────── */
function mentions() {
    const root = $('[data-adm-mentions]');
    const cards = $$('[data-adm-card]', root ?? document);
    if (!root || cards.length < 2) return;

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    root.classList.add('is-live');

    const setOpen = (card, open) => {
        card.classList.toggle('is-open', open);
        $('[data-adm-toggle]', card).setAttribute('aria-expanded', String(open));
    };

    cards.forEach((card) => {
        $('[data-adm-toggle]', card).addEventListener('click', () => {
            const open = !card.classList.contains('is-open');
            const state = Flip.getState(cards);

            cards.forEach((other) => setOpen(other, other === card ? open : false));

            if (reduced) return;
            // Les cartes glissent vers leur nouvelle place ; le contenu de la carte ouverte apparaît par-dessus.
            Flip.from(state, { duration: 0.8, ease: 'expo.inOut', scale: false, simple: true });
            if (open) gsap.fromTo($('[data-adm-body]', card).children, { autoAlpha: 0, y: 16 }, { autoAlpha: 1, y: 0, duration: 0.6, stagger: 0.05, delay: 0.3, ease: 'power3.out' });
        });
    });
}

/* ───────── 5. Dépôt : l'affiche s'incline ───────── */
function tilt() {
    if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;

    $$('[data-adm-tilt]').forEach((link) => {
        const sheet = $('[data-adm-tilt-sheet]', link);
        if (!sheet) return;

        const rotateX = gsap.quickTo(sheet, 'rotationX', { duration: 0.6, ease: 'power3.out' });
        const rotateY = gsap.quickTo(sheet, 'rotationY', { duration: 0.6, ease: 'power3.out' });

        link.addEventListener('pointermove', (event) => {
            const box = sheet.getBoundingClientRect();
            rotateY(((event.clientX - box.left) / box.width - 0.5) * 12);
            rotateX(-((event.clientY - box.top) / box.height - 0.5) * 12);
        });
        link.addEventListener('pointerleave', () => {
            rotateX(0);
            rotateY(0);
        });
    });
}
