// Page « Cafétéria » : sept scènes (voir resources/views/pages/cafeteria.blade.php).
//
//  1. L'assiette     : « Cafétéria » se sert lettre à lettre, la couronne de mots tourne autour de l'assiette (et accélère avec le défilement).
//  2. Trois nourritures : écran épinglé, la citation se révèle phrase après phrase, soulignements tracés (DrawSVG), le fond change de couleur.
//  3. À l'assiette   : six mots entourés « à la main » à leur arrivée et au survol.
//  4. Au menu        : le tableau des repas de la semaine ; onglets par semaine et, sur petit écran, choix du jour (sans animation).
//  5. Le triptyque   : trois volets, celui qu'on choisit s'ouvre (survol, clic ou clavier) ; sur petit écran, tout reste empilé.
//  6. L'ambiance     : bandeau de mots dont la vitesse, le sens et l'inclinaison suivent le défilement de la page.
//  7. L'invitation   : les mots de la phrase arrivent tour à tour de gauche et de droite.
//
// Renforcement progressif : sans JavaScript ou avec « réduire les animations », la page reste complète (voir resources/css/cafeteria.css).
import { DrawSVGPlugin } from 'gsap/DrawSVGPlugin';
import { gsap, ScrollTrigger, SplitText, MOTION_OK } from './gsap.js';

gsap.registerPlugin(DrawSVGPlugin);

const headerHeight = () => document.querySelector('.site-header')?.offsetHeight ?? 0;
const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

export function initCafeteria() {
    // Le triptyque et le menu de la semaine fonctionnent aussi sans animation (changements instantanés).
    triptych();
    menu();

    const mm = gsap.matchMedia();

    mm.add(MOTION_OK, (context) => {
        const run = () => {
            document.documentElement.classList.add('caf-ready');
            hero();
            nourish();
            tiles();
            marquee();
            invite();
            ScrollTrigger.refresh();
        };

        // Les polices doivent être chargées avant de découper le texte en lettres et en mots.
        if (document.fonts?.status === 'loaded') run();
        else document.fonts.ready.then(() => context.add(run));
    });
}

/* ───────── 1. L'assiette ───────── */
function hero() {
    const root = $('[data-caf-hero]');
    if (!root) return;

    // Les lettres tombent dans l'assiette, une à une, et rebondissent.
    SplitText.create($('[data-caf-title]', root), {
        type: 'chars',
        charsClass: 'char',
        autoSplit: true,
        onSplit: (self) => gsap.from(self.chars, { yPercent: -130, rotate: () => gsap.utils.random(-25, 25), opacity: 0, duration: 1.1, stagger: 0.06, ease: 'bounce.out' }),
    });
    const stroke = $('[data-caf-stroke] path', root);
    if (stroke) gsap.fromTo(stroke, { drawSVG: '0%' }, { drawSVG: '100%', duration: 1.2, delay: 0.8, ease: 'power2.inOut' });
    gsap.from($$('[data-caf-fade]', root), { autoAlpha: 0, y: 24, duration: 1, delay: 0.9, ease: 'power3.out' });

    const plate = $('[data-caf-plate]', root);
    const ring = $('[data-caf-ring]', root);
    if (plate) gsap.from(plate, { scale: 0.7, rotate: -18, autoAlpha: 0, duration: 1.4, delay: 0.2, ease: 'expo.out' });

    // La couronne de mots tourne en continu ; le défilement l'accélère.
    if (ring) {
        const spin = gsap.to(ring, { rotation: 360, duration: 40, ease: 'none', repeat: -1, transformOrigin: '50% 50%' });
        ScrollTrigger.create({
            start: 0,
            end: 'max',
            onUpdate: (self) => {
                const boost = 1 + Math.min(Math.abs(self.getVelocity()) / 250, 12);
                gsap.to(spin, { timeScale: boost, duration: 0.2, overwrite: true });
                gsap.to(spin, { timeScale: 1, duration: 1.4, delay: 0.2, ease: 'power2.out', overwrite: 'auto' });
            },
        });
    }

    // La photo respire dans l'assiette quand on descend.
    const img = $('[data-caf-plate-photo] img', root);
    if (img) gsap.fromTo(img, { scale: 1.05 }, { scale: 1.3, yPercent: 6, ease: 'none', scrollTrigger: { trigger: root, start: 'top top', end: 'bottom top', scrub: true } });
}

/* ───────── 2. Trois nourritures ───────── */
function nourish() {
    const root = $('[data-caf-nourish]');
    const stage = $('[data-caf-nourish-stage]', root ?? document);
    const phrases = $$('[data-caf-phrase]', root ?? document);
    if (!root || !stage || !phrases.length) return;

    root.classList.add('is-live');
    const lines = phrases.map((phrase) => ({ phrase, text: $('.caf-phrase__text', phrase), path: $('path', phrase) }));
    const colors = ['#24402a', '#9c3f20', '#1d5470'];

    gsap.set(lines.map((l) => l.text), { opacity: 0.18 });
    gsap.set(lines.map((l) => l.path), { drawSVG: '0%' });
    gsap.set(root, { backgroundColor: colors[0] });

    const tl = gsap.timeline({
        defaults: { ease: 'none' },
        scrollTrigger: {
            trigger: stage,
            start: () => `top top+=${headerHeight()}`,
            end: () => `+=${Math.round(window.innerHeight * 3)}`,
            pin: true,
            scrub: 0.6,
            anticipatePin: 1,
            invalidateOnRefresh: true,
        },
    });

    // Chaque phrase s'allume, son soulignement se trace, et le fond passe à la couleur suivante.
    lines.forEach((line, i) => {
        tl.to(line.text, { opacity: 1, duration: 0.6 }, i);
        tl.to(line.path, { drawSVG: '100%', duration: 0.7 }, i + 0.2);
        if (i > 0) tl.to(root, { backgroundColor: colors[i], duration: 0.5 }, i - 0.1);
    });
    tl.to({}, { duration: 0.35 });
}

/* ───────── 3. À l'assiette ───────── */
function tiles() {
    const items = $$('[data-caf-tile]');
    if (!items.length) return;

    const paths = items.map((tile) => $('path', tile));
    gsap.set(paths, { drawSVG: '0%' });

    // Les mots sont entourés à leur arrivée…
    ScrollTrigger.batch(items, {
        start: 'top 88%',
        once: true,
        onEnter: (batch) => gsap.to(batch.map((tile) => $('path', tile)), { drawSVG: '100%', duration: 0.9, stagger: 0.12, ease: 'power2.inOut', delay: 0.25 }),
    });

    // … et re-dessinés au survol.
    if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        items.forEach((tile) => {
            const path = $('path', tile);
            tile.addEventListener('pointerenter', () => gsap.fromTo(path, { drawSVG: '0%' }, { drawSVG: '100%', duration: 0.6, ease: 'power2.out', overwrite: true }));
        });
    }
}

/* ───────── 4. Au menu ─────────
 * Renforcement progressif du tableau de la semaine (resources/views/pages/cafeteria.blade.php) : onglets par semaine (flèches, début, fin) et, sous 1024 px,
 * choix du jour (aujourd'hui par défaut). Sans ce script, toutes les semaines et tous les jours restent affichés. */
function menu() {
    const root = $('[data-caf-menu]');
    if (!root) return;

    root.classList.add('is-live');

    // Onglets de semaine
    const tabs = $$('[data-caf-week]', root);
    const panels = $$('[data-caf-week-panel]', root);
    if (tabs.length > 1) {
        const select = (id, focus = false) => {
            tabs.forEach((tab) => {
                const on = tab.dataset.cafWeek === id;
                tab.setAttribute('aria-selected', String(on));
                tab.tabIndex = on ? 0 : -1;
                if (on && focus) tab.focus();
            });
            panels.forEach((panel) => {
                panel.hidden = panel.dataset.cafWeekPanel !== id;
            });
        };

        tabs.forEach((tab, i) => {
            tab.addEventListener('click', () => select(tab.dataset.cafWeek));
            tab.addEventListener('keydown', (event) => {
                const target = { ArrowRight: (i + 1) % tabs.length, ArrowLeft: (i - 1 + tabs.length) % tabs.length, Home: 0, End: tabs.length - 1 }[event.key];
                if (target === undefined) return;
                event.preventDefault();
                select(tabs[target].dataset.cafWeek, true);
            });
        });
        select(tabs[0].dataset.cafWeek);
    }

    // Choix du jour, panneau par panneau (visible sous 1024 px seulement : voir cafeteria.css)
    panels.forEach((panel) => {
        const chips = $$('[data-caf-chip]', panel);
        const cells = $$('[data-caf-day]', panel);
        const show = (day) => {
            chips.forEach((chip) => chip.setAttribute('aria-pressed', String(chip.dataset.cafChip === String(day))));
            cells.forEach((cell) => cell.classList.toggle('is-on', cell.dataset.cafDay === String(day)));
        };

        chips.forEach((chip) => chip.addEventListener('click', () => show(chip.dataset.cafChip)));
        show(panel.dataset.cafToday ?? 0);
    });
}

/* ───────── 5. Le triptyque ───────── */
function triptych() {
    const section = $('[data-caf-triptych]');
    const panels = $$('[data-caf-panel]', section ?? document);
    if (!section || panels.length < 2) return;

    const mm = gsap.matchMedia();
    mm.add('(min-width: 1024px)', () => {
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const duration = reduced ? 0 : 0.9;
        section.classList.add('is-live');
        let current = panels.findIndex((panel) => panel.classList.contains('is-open'));
        if (current < 0) current = 0;

        gsap.set(panels, { flexGrow: (i) => (i === current ? 4 : 1) });

        const open = (index) => {
            if (index === current) return;
            current = index;
            panels.forEach((panel, i) => {
                const isOpen = i === index;
                panel.classList.toggle('is-open', isOpen);
                $('[data-caf-open]', panel).setAttribute('aria-expanded', String(isOpen));
                gsap.to(panel, { flexGrow: isOpen ? 4 : 1, duration, ease: 'expo.out', overwrite: 'auto' });
            });
            if (!reduced) gsap.fromTo($('[data-caf-text]', panels[index]), { autoAlpha: 0, y: 18 }, { autoAlpha: 1, y: 0, duration: 0.8, delay: 0.25, ease: 'expo.out' });
        };

        const cleanups = panels.map((panel, i) => {
            const onClick = () => open(i);
            const onFocus = () => open(i);
            const button = $('[data-caf-open]', panel);
            panel.addEventListener('click', onClick);
            button.addEventListener('focus', onFocus);
            // Au survol (souris seulement), avec un court délai pour ne pas s'ouvrir en traversant.
            let timer;
            const onEnter = () => {
                clearTimeout(timer);
                timer = setTimeout(() => open(i), 120);
            };
            const onLeave = () => clearTimeout(timer);
            if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
                panel.addEventListener('pointerenter', onEnter);
                panel.addEventListener('pointerleave', onLeave);
            }

            return () => {
                panel.removeEventListener('click', onClick);
                button.removeEventListener('focus', onFocus);
                panel.removeEventListener('pointerenter', onEnter);
                panel.removeEventListener('pointerleave', onLeave);
            };
        });

        return () => {
            cleanups.forEach((fn) => fn());
            gsap.set(panels, { clearProps: 'flexGrow' });
            section.classList.remove('is-live');
        };
    });

    // Entrée : les volets montent l'un après l'autre.
    mm.add(MOTION_OK, () => {
        gsap.from(panels, { autoAlpha: 0, y: 60, duration: 1, stagger: 0.14, ease: 'expo.out', scrollTrigger: { trigger: section, start: 'top 70%', once: true } });
    });
}

/* ───────── 6. L'ambiance : bandeau défilant ───────── */
function marquee() {
    const track = $('[data-caf-track]');
    if (!track) return;

    // Quatre groupes identiques : on boucle sur la largeur d'un seul (25 %).
    const loop = gsap.to(track, { xPercent: -25, duration: 22, ease: 'none', repeat: -1 });
    const skew = gsap.quickTo(track, 'skewX', { duration: 0.4, ease: 'power3.out' });

    ScrollTrigger.create({
        start: 0,
        end: 'max',
        onUpdate: (self) => {
            const velocity = self.getVelocity();
            // Le sens du défilement de la page décide du sens du bandeau ; sa vitesse l'accélère et l'incline.
            gsap.to(loop, { timeScale: self.direction * (1 + Math.min(Math.abs(velocity) / 200, 8)), duration: 0.25, overwrite: true });
            gsap.to(loop, { timeScale: self.direction, duration: 1.2, delay: 0.25, ease: 'power2.out', overwrite: 'auto' });
            skew(gsap.utils.clamp(-14, 14, -velocity / 120));
            gsap.delayedCall(0.15, () => skew(0));
        },
    });
}

/* ───────── 7. L'invitation ───────── */
function invite() {
    const text = $('[data-caf-invite-text]');
    if (!text) return;

    SplitText.create(text, {
        type: 'words',
        wordsClass: 'word',
        autoSplit: true,
        onSplit: (self) =>
            gsap.from(self.words, {
                x: (i) => (i % 2 ? 160 : -160),
                rotate: (i) => (i % 2 ? 6 : -6),
                opacity: 0,
                ease: 'none',
                stagger: 0.18,
                scrollTrigger: { trigger: text, start: 'top 85%', end: 'bottom 55%', scrub: 0.6 },
            }),
    });
}
