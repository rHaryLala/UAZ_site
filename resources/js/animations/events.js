// Page « Événements » : quatre scènes (voir resources/views/events/index.blade.php).
//
//  1. Le cadran      : l'année est un cercle de douze mois ; le titre se lève lettre à lettre, le cercle se trace, aujourd'hui parcourt le cadran jusqu'à
//                      sa place, les événements à venir se posent sur le cercle et un arc relie aujourd'hui au prochain. Le compte à rebours du
//                      prochain rendez-vous avance chaque seconde (les chiffres roulent).
//  2. Le programme   : un fil se remplit au fil du défilement, chaque rendez-vous allume son point, sa date monte, sa carte glisse et sa photo se
//                      dévoile (rideau) puis glisse dans son cadre ; un curseur « Voir » accompagne les cartes (souris).
//  3. Les archives   : les événements passés, en noir et blanc, arrivent par vagues et retrouvent leurs couleurs au survol.
//  4. La suite       : les mots de la phrase arrivent tour à tour.
//  Page d'un événement : la photo respire et glisse, le titre se lève mot à mot, le billet se pose (et suit le pointeur), le compte à rebours
//  avance ; le déroulé (la plage horaire, ou les jours) se remplit ; le descriptif apparaît en lisant ; les autres rendez-vous arrivent par vagues.
//
// Renforcement progressif : sans JavaScript ou avec « réduire les animations », la page reste complète (voir resources/css/events.css) ;
// avec « réduire les animations », le compte à rebours avance aussi, sans mouvement.
import { DrawSVGPlugin } from 'gsap/DrawSVGPlugin';
import { gsap, ScrollTrigger, SplitText, MOTION_OK, NO_MOTION } from './gsap.js';
import { revealProse } from './prose.js';

gsap.registerPlugin(DrawSVGPlugin);

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

export function initEvents() {
    const mm = gsap.matchMedia();

    mm.add(MOTION_OK, (context) => {
        let stop = () => {};
        const run = () => {
            document.documentElement.classList.add('evt-ready');
            hero();
            showHero();
            stop = countdown(true);
            upcoming();
            when();
            revealProse($('[data-evs-prose] .prose'));
            past();
            more();
            ScrollTrigger.refresh();
        };

        // Les polices doivent être chargées avant de découper le texte en lettres et en mots.
        if (document.fonts?.status === 'loaded') run();
        else document.fonts.ready.then(() => context.add(run));

        return () => stop();
    });

    // Mouvement réduit : le compte à rebours avance, sans animation.
    mm.add(NO_MOTION, () => countdown(false));
}

/* ───────── Compte à rebours ───────── */
function countdown(animated) {
    const root = $('[data-evt-countdown]');
    if (!root) return () => {};

    const target = new Date(root.dataset.evtCountdown).getTime();
    const cells = Object.fromEntries($$('[data-unit]', root).map((el) => [el.dataset.unit, el]));

    const tick = () => {
        const left = Math.max(0, Math.floor((target - Date.now()) / 1000));
        const values = { d: Math.floor(left / 86400), h: Math.floor((left % 86400) / 3600), m: Math.floor((left % 3600) / 60), s: left % 60 };

        Object.entries(values).forEach(([unit, value]) => {
            const el = cells[unit];
            const text = String(value).padStart(2, '0');
            if (!el || el.textContent === text) return;
            el.textContent = text;
            // Le chiffre qui change roule vers le haut.
            if (animated) gsap.fromTo(el, { yPercent: 60, opacity: 0 }, { yPercent: 0, opacity: 1, duration: 0.4, ease: 'power3.out', overwrite: true });
        });

        return left;
    };

    tick();
    const timer = setInterval(() => {
        if (tick() === 0) clearInterval(timer);
    }, 1000);

    return () => clearInterval(timer);
}

/* ───────── 1. Le cadran ───────── */
function hero() {
    const root = $('[data-evt-hero]');
    if (!root) return;

    SplitText.create($('[data-evt-title]', root), {
        type: 'words,chars',
        wordsClass: 'word',
        charsClass: 'char',
        autoSplit: true,
        onSplit: (self) => gsap.from(self.chars, { yPercent: 110, opacity: 0, duration: 1, stagger: 0.035, ease: 'power4.out' }),
    });
    gsap.from($$('[data-evt-fade]', root), { autoAlpha: 0, y: 22, duration: 1, delay: 0.6, stagger: 0.14, ease: 'power3.out' });

    const dial = $('[data-evt-dial]', root);
    if (!dial) return;

    // Le cercle se trace, les mois apparaissent.
    gsap.fromTo($('[data-evt-ring]', dial), { drawSVG: '0%' }, { drawSVG: '100%', duration: 1.8, ease: 'power2.inOut', delay: 0.2 });
    gsap.from($$('[data-evt-tick]', dial), { opacity: 0, duration: 0.5, stagger: 0.06, delay: 0.5 });
    gsap.from($$('[data-evt-month]', dial), { opacity: 0, duration: 0.6, stagger: 0.06, delay: 0.7 });

    // Aujourd'hui et les événements à venir partent du 1er janvier et glissent jusqu'à leur place sur le cercle.
    const turns = $$('[data-evt-turn]', dial);
    gsap.set(turns, { svgOrigin: '200 200' });
    turns.forEach((turn) => {
        const angle = Number(turn.dataset.evtTurn);
        gsap.fromTo(turn, { rotation: 0 }, { rotation: angle, duration: 1.2 + angle / 180, ease: 'power3.inOut', delay: 0.9 });
    });

    // L'arc qui mène d'aujourd'hui au prochain rendez-vous se dessine quand les deux repères sont en place.
    const arc = $('[data-evt-arc]', dial);
    if (arc) gsap.fromTo(arc, { drawSVG: '0%' }, { drawSVG: '100%', duration: 1.6, ease: 'power2.inOut', delay: 2.4 });

    // Le prochain rendez-vous bat doucement.
    const next = $('[data-evt-next-dot]', dial);
    if (next) gsap.to(next, { scale: 1.45, transformOrigin: '50% 50%', duration: 0.9, ease: 'sine.inOut', repeat: -1, yoyo: true, delay: 3 });

    // Au défilement, le cadran tourne lentement et s'éloigne.
    gsap.to(dial, { rotation: -28, scale: 0.9, opacity: 0.5, ease: 'none', scrollTrigger: { trigger: root, start: 'top top', end: 'bottom top', scrub: true } });
}

/* ───────── 2. Le programme ───────── */
function upcoming() {
    const root = $('[data-evt-upcoming]');
    if (!root) return;

    // Le fil se remplit avec le défilement.
    const line = $('[data-evt-line]', root);
    const fill = $('[data-evt-fill]', root);
    if (line && fill) {
        gsap.fromTo(fill, { scaleY: 0 }, { scaleY: 1, ease: 'none', scrollTrigger: { trigger: line, start: 'top 60%', end: 'bottom 60%', scrub: 0.4 } });
    }

    $$('[data-evt-item]', root).forEach((item, i) => {
        const node = $('[data-evt-node]', item);
        const day = $('[data-evt-day]', item);
        const card = $('[data-evt-card]', item);
        const media = $('[data-evt-media]', item);
        const img = $('[data-evt-img]', item);
        const once = { trigger: item, start: 'top 82%', once: true };

        if (node) {
            ScrollTrigger.create({
                trigger: item,
                start: 'top 60%',
                onEnter: () => node.classList.add('is-lit'),
                onLeaveBack: () => node.classList.remove('is-lit'),
            });
        }
        if (day) gsap.from(day, { yPercent: 40, opacity: 0, duration: 1, ease: 'power4.out', scrollTrigger: once });
        if (card) gsap.from(card, { x: 70, opacity: 0, duration: 1.1, ease: 'power3.out', scrollTrigger: once });
        // La photo se dévoile par un rideau, puis glisse dans son cadre.
        if (media) gsap.fromTo(media, { clipPath: 'inset(0% 100% 0% 0%)' }, { clipPath: 'inset(0% 0% 0% 0%)', duration: 1.2, delay: 0.25, ease: 'power4.inOut', scrollTrigger: once, onComplete: () => gsap.set(media, { clearProps: 'clipPath' }) });
        if (img) gsap.fromTo(img, { scale: 1.25, yPercent: -6 }, { scale: 1.25, yPercent: 6, ease: 'none', scrollTrigger: { trigger: item, start: 'top bottom', end: 'bottom top', scrub: true } });
    });

    cursor(root);
}

/** Curseur « Voir » au-dessus des cartes : uniquement avec une vraie souris. */
function cursor(root) {
    const dot = $('[data-evt-cursor]', root);
    if (!dot || !window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;

    root.classList.add('is-fine');
    gsap.set(dot, { scale: 0 });
    const x = gsap.quickTo(dot, 'x', { duration: 0.35, ease: 'power3.out' });
    const y = gsap.quickTo(dot, 'y', { duration: 0.35, ease: 'power3.out' });

    root.addEventListener('pointermove', (event) => {
        x(event.clientX);
        y(event.clientY);
    });
    root.addEventListener('pointerover', (event) => {
        if (event.target.closest('[data-evt-link], .evt-card__media')) gsap.to(dot, { scale: 1, opacity: 1, duration: 0.35, ease: 'back.out(2)', overwrite: 'auto' });
    });
    root.addEventListener('pointerout', (event) => {
        if (event.target.closest('[data-evt-link], .evt-card__media') && !event.relatedTarget?.closest?.('[data-evt-link], .evt-card__media')) {
            gsap.to(dot, { scale: 0, opacity: 0, duration: 0.25, overwrite: 'auto' });
        }
    });
}

/* ───────── 3. Les archives ───────── */
function past() {
    const stamps = $$('[data-evt-stamp]');
    if (!stamps.length) return;

    // Les événements arrivent par vagues, à mesure qu'ils entrent dans l'écran.
    ScrollTrigger.batch(stamps, {
        start: 'top 90%',
        once: true,
        onEnter: (batch) => gsap.from(batch, { y: 70, opacity: 0, rotation: () => gsap.utils.random(-3, 3), duration: 1, stagger: 0.12, ease: 'power3.out' }),
    });
}

/* ───────── 4. La suite ───────── */
function more() {
    const root = $('[data-evt-more]');
    const text = $('[data-evt-more-text]', root ?? document);
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

/* ───────── Page d'un événement ───────── */
function showHero() {
    const root = $('[data-evs-hero]');
    if (!root) return;

    SplitText.create($('[data-evs-title]', root), {
        type: 'words',
        wordsClass: 'word',
        autoSplit: true,
        onSplit: (self) => gsap.from(self.words, { yPercent: 60, opacity: 0, duration: 1, stagger: 0.06, ease: 'power4.out' }),
    });
    gsap.from($$('[data-evs-fade]', root), { autoAlpha: 0, y: 22, duration: 1, delay: 0.5, stagger: 0.12, ease: 'power3.out' });

    // La photo se pose, puis glisse plus lentement que la page.
    const cover = $('[data-evs-cover]', root);
    if (cover) {
        gsap.from(cover, { scale: 1.2, duration: 2.4, ease: 'power3.out' });
        gsap.to(cover, { yPercent: 14, scale: 1.08, ease: 'none', scrollTrigger: { trigger: root, start: 'top top', end: 'bottom top', scrub: true } });
    }

    // Le billet se pose en pivotant, puis suit le pointeur (souris uniquement).
    const ticket = $('[data-evs-ticket]', root);
    if (ticket) {
        gsap.from(ticket, { autoAlpha: 0, y: 70, rotation: 5, duration: 1.3, delay: 0.7, ease: 'back.out(1.4)' });
        if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
            const tiltX = gsap.quickTo(ticket, 'rotationX', { duration: 0.8, ease: 'power3.out' });
            const tiltY = gsap.quickTo(ticket, 'rotationY', { duration: 0.8, ease: 'power3.out' });
            gsap.set(ticket, { transformPerspective: 900 });
            root.addEventListener('pointermove', (event) => {
                const rect = root.getBoundingClientRect();
                tiltY(((event.clientX - rect.left) / rect.width - 0.5) * 12);
                tiltX(-((event.clientY - rect.top) / rect.height - 0.5) * 8);
            });
            root.addEventListener('pointerleave', () => {
                tiltX(0);
                tiltY(0);
            });
        }
    }
}

/** Le déroulé : la plage horaire se remplit, ou les jours de l'événement se posent l'un après l'autre. */
function when() {
    const root = $('[data-evs-when]');
    if (!root) return;

    const trigger = { trigger: root, start: 'top 70%', once: true };
    const seg = $('[data-evs-seg]', root);
    if (seg) {
        const dot = seg.classList.contains('evs-track__dot');
        gsap.from(seg, dot ? { scale: 0, duration: 0.8, ease: 'back.out(3)', scrollTrigger: trigger } : { scaleX: 0, duration: 1.4, ease: 'power3.inOut', scrollTrigger: trigger });
    }
    const days = $$('[data-evs-day]', root);
    if (days.length) gsap.from(days, { y: 40, opacity: 0, duration: 0.8, stagger: 0.09, ease: 'back.out(1.6)', scrollTrigger: trigger });
}
