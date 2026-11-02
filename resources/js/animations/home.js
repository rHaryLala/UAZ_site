// Accueil : onze scènes (voir resources/views/home/*.blade.php). Le héros (hero.js) et « Votre futur commence ici » (future-title.js, vidéo) ne sont pas touchés.
//
//  1. La différence   : la phrase s'allume mot à mot sur un écran arrêté ; les chiffres clés se comptent (counters.js), leur filet d'or se trace.
//  2. Les domaines    : quatre panneaux qui s'ouvrent au survol, au clavier ou au toucher ; le panneau ouvert dévoile ses mentions.
//  3. Admissions      : le sceau s'incline vers le pointeur dans deux anneaux tracés, la devise tourne autour ; les boutons sont attirés par le pointeur.
//  4. La vie à l'UAZ  : les lieux s'empilent, chacun recouvre le précédent en le reculant (écran arrêté sur grand écran).
//  5. Pratique        : le titre géant défile avec la page.
//  6. Témoignages     : la grande citation arrive mot à mot.
//  7. Actualités      : vignettes éditoriales (news.js) ; 8. Événements : billets (compte à rebours : events.js) ; 9. Ressources : sommaire à image suiveuse ;
//  10. Partenaires    : ruban de logos ; 11. Fermeture : la photo glisse, le titre arrive mot à mot.
//
// Renforcement progressif : sans JavaScript ou avec « réduire les animations », l'accueil reste complet (voir resources/css/home.css) ;
// les panneaux de formation s'ouvrent quand même au survol et au clavier.
import { DrawSVGPlugin } from 'gsap/DrawSVGPlugin';
import { gsap, ScrollTrigger, SplitText, MOTION_OK, NO_MOTION } from './gsap.js';

gsap.registerPlugin(DrawSVGPlugin);

const headerHeight = () => document.querySelector('.site-header')?.offsetHeight ?? 0;
const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
const fine = () => window.matchMedia('(hover: hover) and (pointer: fine)').matches;

export function initHome() {
    const mm = gsap.matchMedia();

    mm.add(MOTION_OK, (context) => {
        const run = () => {
            difference();
            domains();
            admissions();
            life();
            practice();
            voices();
            events();
            resources();
            partners();
            closing();
            magnets();
            ScrollTrigger.refresh();
        };

        // Les polices doivent être chargées avant de découper le texte en mots.
        if (document.fonts?.status === 'loaded') run();
        else document.fonts.ready.then(() => context.add(run));
    });

    // Mouvement réduit : seuls les panneaux de formation réagissent (ouverture), sans transition de mouvement.
    mm.add(NO_MOTION, () => accordion());
}

/* ───────── 1. La différence ───────── */
function difference() {
    const stage = $('[data-home-stage]');
    const title = $('[data-home-light]', stage ?? document);
    const after = $('[data-home-after]', stage ?? document);
    if (stage && title) {
        const mm = gsap.matchMedia();
        const build = (pinned) => {
            const split = SplitText.create(title, { type: 'words', wordsClass: 'word' });
            // Éteints, les mots restent lisibles (contraste d'au moins 3:1 pour du grand texte) : ils s'allument sans jamais disparaître.
            gsap.set(split.words, { opacity: 0.5 });
            if (after) gsap.set(after, { autoAlpha: 0, y: 30 });

            const tl = gsap.timeline({
                defaults: { ease: 'none' },
                scrollTrigger: {
                    trigger: stage,
                    start: pinned ? () => `top top+=${headerHeight()}` : 'top 70%',
                    end: pinned ? () => `+=${Math.round(window.innerHeight * 1.5)}` : 'bottom 60%',
                    pin: pinned,
                    scrub: 0.6,
                    anticipatePin: 1,
                    invalidateOnRefresh: true,
                },
            });
            tl.to(split.words, { opacity: 1, stagger: 0.1, duration: 0.5 });
            if (after) tl.to(after, { autoAlpha: 1, y: 0, duration: 1.6 }, '>-0.4');

            return () => split.revert();
        };
        mm.add('(min-width: 1024px)', () => build(true));
        mm.add('(max-width: 1023px)', () => build(false));
    }

    // Les chiffres : le filet d'or se trace, le bloc monte (les nombres se comptent dans counters.js).
    $$('[data-home-fig]').forEach((fig, i) => {
        const trigger = { trigger: fig, start: 'top 88%', once: true };
        gsap.fromTo($('.home-fig__rule', fig), { scaleX: 0 }, { scaleX: 1, duration: 1.2, delay: i * 0.1, ease: 'power3.inOut', scrollTrigger: trigger });
        gsap.from(fig, { y: 50, opacity: 0, duration: 1, delay: i * 0.1, ease: 'power3.out', scrollTrigger: trigger });
    });
}

/* ───────── 2. Les domaines de formation ───────── */
function accordion() {
    const row = $('[data-home-dom]');
    if (!row) return;
    const panels = $$('[data-home-panel]', row);
    const wide = window.matchMedia('(min-width: 1024px)');
    const open = (panel) => panels.forEach((p) => p.classList.toggle('is-open', p === panel));

    const onEnter = (event) => wide.matches && open(event.currentTarget);
    panels.forEach((panel) => {
        panel.addEventListener('pointerenter', onEnter);
        panel.addEventListener('focusin', onEnter);
        // Au toucher, un appui sur le panneau l'ouvre (le lien d'une mention reste un lien).
        panel.addEventListener('click', (event) => wide.matches && !event.target.closest('a') && open(panel));
    });
    if (wide.matches) open(panels[0]);

    return () => panels.forEach((panel) => {
        panel.removeEventListener('pointerenter', onEnter);
        panel.removeEventListener('focusin', onEnter);
        panel.classList.remove('is-open');
    });
}

function domains() {
    accordion();
    const row = $('[data-home-dom]');
    if (!row) return;
    gsap.from($$('[data-home-panel]', row), { yPercent: 14, opacity: 0, duration: 1.1, stagger: 0.12, ease: 'power3.out', scrollTrigger: { trigger: row, start: 'top 85%', once: true } });
}

/* ───────── 3. Admissions ───────── */
function admissions() {
    const root = $('[data-home="adm"]');
    const seal = $('[data-home-seal]', root ?? document);
    if (!root || !seal) return;

    const rings = $$('circle', seal);
    gsap.fromTo(rings, { drawSVG: '0%' }, { drawSVG: '100%', duration: 2, stagger: 0.3, ease: 'power2.inOut', scrollTrigger: { trigger: seal, start: 'top 80%', once: true } });
    gsap.to($('[data-home-spin]', seal), { rotation: 360, svgOrigin: '200 200', duration: 70, ease: 'none', repeat: -1 });
    gsap.from($('[data-home-seal-img]', seal), { scale: 0.7, opacity: 0, duration: 1.4, ease: 'back.out(1.4)', scrollTrigger: { trigger: seal, start: 'top 80%', once: true } });

    // Le sceau s'incline vers le pointeur.
    if (fine()) {
        const img = $('[data-home-seal-img]', seal);
        gsap.set(img, { transformPerspective: 900 });
        const rx = gsap.quickTo(img, 'rotationX', { duration: 0.8, ease: 'power3.out' });
        const ry = gsap.quickTo(img, 'rotationY', { duration: 0.8, ease: 'power3.out' });
        root.addEventListener('pointermove', (event) => {
            const rect = seal.getBoundingClientRect();
            ry(gsap.utils.clamp(-22, 22, ((event.clientX - (rect.left + rect.width / 2)) / rect.width) * 30));
            rx(gsap.utils.clamp(-18, 18, -((event.clientY - (rect.top + rect.height / 2)) / rect.height) * 24));
        });
        root.addEventListener('pointerleave', () => {
            rx(0);
            ry(0);
        });
    }
}

/* ───────── 4. La vie à l'UAZ ───────── */
function life() {
    const cards = $$('[data-home-card]');
    if (!cards.length) return;

    const mm = gsap.matchMedia();

    // Grand écran : chaque carte reste en place pendant que la suivante la recouvre ; la carte recouverte recule et s'assombrit.
    mm.add('(min-width: 1024px)', () => {
        const last = cards.at(-1);
        cards.forEach((card, i) => {
            const inner = $('[data-home-card-inner]', card);
            const img = $('[data-home-card-img]', card);
            const body = $('.home-card__body', card);
            const startAt = () => `top top+=${headerHeight()}`;

            if (i < cards.length - 1) {
                // On épingle le contenu de la carte (et non l'élément de liste) : la liste ne contient ainsi que ses éléments.
                ScrollTrigger.create({ trigger: card, start: startAt, endTrigger: last, end: startAt, pin: inner, pinSpacing: false, anticipatePin: 1 });
                gsap.to(inner, { scale: 0.92, filter: 'brightness(0.45)', ease: 'none', scrollTrigger: { trigger: cards[i + 1], start: 'top bottom', end: startAt, scrub: true } });
            }
            if (img) gsap.fromTo(img, { yPercent: -6, scale: 1.12 }, { yPercent: 6, scale: 1.12, ease: 'none', scrollTrigger: { trigger: card, start: 'top bottom', end: 'bottom top', scrub: true } });
            if (body) gsap.from(body.children, { y: 40, opacity: 0, duration: 1, stagger: 0.1, ease: 'power3.out', scrollTrigger: { trigger: card, start: 'top 60%', once: true } });
        });
    });

    // Petit écran : les cartes s'empilent normalement et montent une à une.
    mm.add('(max-width: 1023px)', () => {
        cards.forEach((card) => {
            const body = $('.home-card__body', card);
            if (body) gsap.from(body.children, { y: 40, opacity: 0, duration: 0.9, stagger: 0.1, ease: 'power3.out', scrollTrigger: { trigger: card, start: 'top 75%', once: true } });
        });
    });
}

/* ───────── 5. Apprentissage pratique ───────── */
function practice() {
    const root = $('[data-home="practice"]');
    const belt = $('[data-home-belt]', root ?? document);
    if (!root || !belt) return;

    gsap.fromTo(belt, { xPercent: 0 }, { xPercent: -50, ease: 'none', scrollTrigger: { trigger: root, start: 'top bottom', end: 'bottom top', scrub: 1 } });
}

/* ───────── 6. Témoignages ───────── */
function voices() {
    const root = $('[data-home="voices"]');
    if (!root) return;

    const mark = $('.home-voices__mark', root);
    if (mark) gsap.to(mark, { yPercent: 25, xPercent: -6, ease: 'none', scrollTrigger: { trigger: root, start: 'top bottom', end: 'bottom top', scrub: true } });

    // La première citation arrive mot à mot (les autres défilent au doigt ou aux boutons, sans effet pour ne pas gêner la lecture).
    const quote = $('[data-home-quote] blockquote', root);
    if (quote) {
        SplitText.create(quote, {
            type: 'words',
            wordsClass: 'word',
            autoSplit: true,
            onSplit: (self) => gsap.from(self.words, { opacity: 0, yPercent: 40, duration: 0.8, stagger: 0.035, ease: 'power3.out', scrollTrigger: { trigger: quote, start: 'top 80%', once: true } }),
        });
    }
}

/* ───────── 8. Événements ───────── */
function events() {
    const tickets = $$('[data-home-ticket]');
    if (!tickets.length) return;

    gsap.from(tickets, { y: 90, rotation: (i) => [-3, 2.5, -2][i % 3], opacity: 0, duration: 1.2, stagger: 0.15, ease: 'power3.out', scrollTrigger: { trigger: tickets[0], start: 'top 88%', once: true } });
}

/* ───────── 9. Ressources ───────── */
function resources() {
    const list = $('[data-home-res]');
    if (!list) return;

    const items = $$('[data-home-res-item]', list);
    gsap.from(items, { y: 40, opacity: 0, duration: 0.9, stagger: 0.12, ease: 'power3.out', scrollTrigger: { trigger: list, start: 'top 85%', once: true } });

    // L'image du lieu apparaît et suit le pointeur dans la ligne (souris uniquement).
    if (!fine()) return;
    list.closest('section')?.classList.add('is-fine');
    items.forEach((item) => {
        const peek = $('[data-home-peek]', item);
        if (!peek) return;
        gsap.set(peek, { xPercent: -50, yPercent: -50 });
        const x = gsap.quickTo(peek, 'x', { duration: 0.5, ease: 'power3.out' });
        const y = gsap.quickTo(peek, 'y', { duration: 0.5, ease: 'power3.out' });
        item.addEventListener('pointerenter', (event) => {
            const rect = item.getBoundingClientRect();
            gsap.set(peek, { x: event.clientX - rect.left, y: event.clientY - rect.top });
            gsap.to(peek, { opacity: 1, scale: 1, rotation: gsap.utils.random(-4, 4), duration: 0.5, ease: 'back.out(1.6)', overwrite: 'auto' });
        });
        item.addEventListener('pointermove', (event) => {
            const rect = item.getBoundingClientRect();
            x(event.clientX - rect.left);
            y(event.clientY - rect.top);
        });
        item.addEventListener('pointerleave', () => gsap.to(peek, { opacity: 0, scale: 0.85, duration: 0.3, overwrite: 'auto' }));
    });
}

/* ───────── 10. Partenaires ───────── */
function partners() {
    const belt = $('[data-home-belt-partners]');
    const track = $('[data-home-partners-track]', belt ?? document);
    if (!belt || !track) return;

    // Une moitié assez large pour remplir l'écran, recopiée : décaler de la moitié boucle sans coupure.
    const originals = [...track.children];
    const half = document.createDocumentFragment();
    belt.classList.add('is-marquee');
    let copies = 1;
    while (track.scrollWidth * copies < window.innerWidth * 1.2 && copies < 8) copies++;
    for (let c = 1; c < copies; c++) originals.forEach((li) => half.append(li.cloneNode(true)));
    track.append(half);
    [...track.children].forEach((li) => track.append(li.cloneNode(true)));
    $$('li', track).slice(originals.length).forEach((li) => {
        li.setAttribute('aria-hidden', 'true');
        $$('a', li).forEach((a) => a.setAttribute('tabindex', '-1'));
    });

    const loop = gsap.to(track, { xPercent: -50, ease: 'none', duration: 30 + copies * originals.length * 3, repeat: -1 });
    belt.addEventListener('pointerenter', () => gsap.to(loop, { timeScale: 0.15, duration: 0.6 }));
    belt.addEventListener('pointerleave', () => gsap.to(loop, { timeScale: 1, duration: 0.6 }));
}

/* ───────── 11. Fermeture ───────── */
function closing() {
    const root = $('[data-home="close"]');
    if (!root) return;

    const bg = $('[data-home-close-bg]', root);
    if (bg) gsap.fromTo(bg, { yPercent: -8 }, { yPercent: 8, ease: 'none', scrollTrigger: { trigger: root, start: 'top bottom', end: 'bottom top', scrub: true } });

    const title = $('[data-home-close-title]', root);
    if (title) {
        SplitText.create(title, {
            type: 'words',
            wordsClass: 'word',
            autoSplit: true,
            onSplit: (self) => gsap.from(self.words, { xPercent: (i) => (i % 2 ? 50 : -50), opacity: 0, duration: 1, stagger: 0.08, ease: 'power3.out', scrollTrigger: { trigger: root, start: 'top 65%', once: true } }),
        });
    }
}

/* ───────── Les boutons attirés par le pointeur ───────── */
function magnets() {
    if (!fine()) return;

    $$('[data-home-magnet]').forEach((button) => {
        const x = gsap.quickTo(button, 'x', { duration: 0.5, ease: 'power3.out' });
        const y = gsap.quickTo(button, 'y', { duration: 0.5, ease: 'power3.out' });
        button.addEventListener('pointermove', (event) => {
            const rect = button.getBoundingClientRect();
            x((event.clientX - (rect.left + rect.width / 2)) * 0.3);
            y((event.clientY - (rect.top + rect.height / 2)) * 0.4);
        });
        button.addEventListener('pointerleave', () => {
            x(0);
            y(0);
        });
    });
}
