// Page « Association des étudiants » : six scènes (voir resources/views/pages/association.blade.php).
//
//  1. « SA »            : le titre se lève lettre à lettre ; les lettres géantes, remplies d'une photo, suivent le pointeur et la photo glisse au défilement.
//  2. Son rôle          : la phrase s'allume mot à mot, puis la chaîne VRAE → SA → activités se trace (nœuds et traits).
//  3. Ses officiants    : une foule de points s'allume (tous les étudiants inscrits votent), puis quelques-uns grandissent (les élus).
//  4. Se présenter      : écran arrêté (grand écran) ; quinze sièges se posent en demi-cercle, une onde figure l'appel à candidature, puis le VRAE
//                         apparaît et se relie à l'équipe (entretien). Le texte de chaque étape remplace le précédent.
//  5. Son engagement    : les projets du VRAE passent en tête de la planification ; les trois principes se posent.
//
// Renforcement progressif : sans JavaScript, avec « réduire les animations » ou sur petit écran, la page reste complète (voir resources/css/association.css) :
// sièges remplis, VRAE et lien visibles, étapes lisibles l'une sous l'autre, planification déjà ordonnée.
import { DrawSVGPlugin } from 'gsap/DrawSVGPlugin';
import { gsap, ScrollTrigger, SplitText, MOTION_OK } from './gsap.js';

gsap.registerPlugin(DrawSVGPlugin);

const headerHeight = () => document.querySelector('.site-header')?.offsetHeight ?? 0;
const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

export function initAssociation() {
    const mm = gsap.matchMedia();

    mm.add(MOTION_OK, (context) => {
        const run = () => {
            document.documentElement.classList.add('sa-ready');
            hero();
            statements();
            role();
            crowd();
            path();
            engagement();
            ScrollTrigger.refresh();
        };

        // Les polices doivent être chargées avant de découper le texte en lettres et en mots.
        if (document.fonts?.status === 'loaded') run();
        else document.fonts.ready.then(() => context.add(run));
    });
}

/* ───────── 1. « SA » ───────── */
function hero() {
    const root = $('[data-sa-hero]');
    if (!root) return;

    SplitText.create($('[data-sa-title]', root), {
        type: 'words,chars',
        wordsClass: 'word',
        charsClass: 'char',
        autoSplit: true,
        onSplit: (self) =>
            gsap.from(self.chars, {
                yPercent: 110,
                opacity: 0,
                duration: 1,
                stagger: 0.03,
                ease: 'power4.out',
            }),
    });
    gsap.from($$('[data-sa-fade]', root), {
        autoAlpha: 0,
        y: 22,
        duration: 1,
        delay: 0.6,
        stagger: 0.12,
        ease: 'power3.out',
    });

    const letters = $$('[data-sa-letter]', root);
    gsap.from(letters, {
        yPercent: 40,
        opacity: 0,
        rotate: (i) => (i ? 6 : -6),
        duration: 1.3,
        delay: 0.4,
        stagger: 0.18,
        ease: 'power4.out',
    });

    // La photo glisse dans les lettres quand on fait défiler la page.
    gsap.fromTo(
        letters,
        { '--sa-py': '15%' },
        {
            '--sa-py': '85%',
            ease: 'none',
            scrollTrigger: { trigger: root, start: 'top top', end: 'bottom top', scrub: true },
        },
    );

    // Chaque lettre suit le pointeur, d'autant plus qu'elle est « proche ».
    if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        const moves = letters.map((letter, i) => [
            gsap.quickTo(letter, 'x', { duration: 0.9, ease: 'power3.out' }),
            gsap.quickTo(letter, 'y', { duration: 0.9, ease: 'power3.out' }),
            (i + 1) * 14,
        ]);
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

/* ───────── Les phrases qui s'allument mot à mot ───────── */
function statements() {
    $$('[data-sa-statement]').forEach((el) => {
        // Opacité de départ : assez haute pour que le texte reste lisible (contraste) avant que la phrase ne s'allume.
        const from = Number(el.dataset.saStatement) || 0.5;
        SplitText.create(el, {
            type: 'words',
            wordsClass: 'word',
            autoSplit: true,
            onSplit: (self) =>
                gsap.fromTo(
                    self.words,
                    { opacity: from },
                    {
                        opacity: 1,
                        ease: 'none',
                        stagger: 0.12,
                        scrollTrigger: { trigger: el, start: 'top 82%', end: 'bottom 52%', scrub: 0.5 },
                    },
                ),
        });
    });
}

/* ───────── 2. Son rôle ───────── */
function role() {
    const chain = $('[data-sa-chain]');
    if (!chain) return;

    const nodes = $$('[data-sa-node]', chain);
    const links = $$('[data-sa-link]', chain);
    const tl = gsap.timeline({ scrollTrigger: { trigger: chain, start: 'top 80%', once: true } });

    nodes.forEach((node, i) => {
        tl.from(node, { x: 50, autoAlpha: 0, duration: 0.7, ease: 'power3.out' }, i ? '>-0.05' : 0);
        const link = links[i];
        if (link) {
            tl.from(
                $('.sa-link__line', link),
                { scaleY: 0, duration: 0.55, ease: 'power2.inOut' },
                '>-0.1',
            ).from(
                $('.sa-link__label', link),
                { autoAlpha: 0, x: -14, duration: 0.5, ease: 'power2.out' },
                '<0.15',
            );
        }
    });
}

/* ───────── 3. Ses officiants ───────── */
function crowd() {
    const root = $('[data-sa-crowd]');
    if (!root) return;

    const dots = $$('i', root);
    const elected = dots.filter((dot) => dot.classList.contains('is-elected'));
    gsap.set(dots, { scale: 0.45, opacity: 0.25, backgroundColor: 'rgba(255, 255, 255, 0.18)' });
    gsap.set(elected, { boxShadow: '0 0 0 rgba(224, 185, 87, 0)' });

    gsap.timeline({ scrollTrigger: { trigger: root, start: 'top 80%', once: true } })
        // Tous les étudiants inscrits votent : les points s'allument dans le désordre.
        .to(dots, {
            scale: 1,
            opacity: 1,
            backgroundColor: 'rgba(224, 185, 87, 0.5)',
            duration: 0.5,
            stagger: { each: 0.016, from: 'random' },
            ease: 'power2.out',
        })
        // Puis les élus se détachent de la foule.
        .to(
            elected,
            {
                scale: 1.55,
                backgroundColor: '#e0b957',
                boxShadow: '0 0 1.1rem rgba(224, 185, 87, 0.8)',
                duration: 0.8,
                stagger: 0.12,
                ease: 'back.out(3)',
            },
            '>0.15',
        );
}

/* ───────── 4. Se présenter ───────── */
function path() {
    const root = $('[data-sa-path]');
    const stage = $('[data-sa-stage]', root ?? document);
    const hemi = $('[data-sa-hemi]', root ?? document);
    if (!root || !stage || !hemi) return;

    const steps = $$('[data-sa-step]', root);
    const seats = $$('[data-sa-seat]', hemi);
    const arc = $('[data-sa-arc]', hemi);
    const rings = $$('[data-sa-rings] circle', hemi);
    const num = $('[data-sa-num]', hemi);
    const caps = [1, 2, 3].map((n) => $(`[data-sa-cap="${n}"]`, hemi));
    const vrae = $('[data-sa-vrae]', hemi);
    const vlink = $('[data-sa-vlink]', hemi);
    const total = Number(num?.dataset.final ?? 15);
    const state = { n: 0 };
    const count = () => {
        if (num) num.textContent = String(Math.round(state.n));
    };
    const pulse = (tl, at) =>
        tl.fromTo(
            rings,
            { attr: { r: 60 }, opacity: 0.9 },
            {
                attr: { r: 330 },
                opacity: 0,
                duration: 1,
                stagger: 0.22,
                ease: 'power1.out',
                immediateRender: false,
            },
            at,
        );

    const mm = gsap.matchMedia();

    // Grand écran : la page s'arrête ; sièges, appel, puis entretien.
    mm.add('(min-width: 1024px)', () => {
        root.classList.add('is-pinned');
        gsap.set(seats, { scale: 0, opacity: 0, transformOrigin: '50% 50%' });
        gsap.set(arc, { drawSVG: '0%' });
        gsap.set(steps, { autoAlpha: 0, y: 30 });
        gsap.set(steps[0], { autoAlpha: 1, y: 0 });
        gsap.set(caps[0], { opacity: 1 });
        gsap.set([caps[1], caps[2]], { opacity: 0 });
        gsap.set(vrae, { autoAlpha: 0, y: -24 });
        gsap.set(vlink, { drawSVG: '0%' });
        state.n = 0;
        count();

        const tl = gsap.timeline({
            defaults: { ease: 'none' },
            scrollTrigger: {
                trigger: stage,
                start: () => `top top+=${headerHeight()}`,
                end: () => `+=${Math.round(window.innerHeight * 2.4)}`,
                pin: true,
                scrub: 0.6,
                anticipatePin: 1,
                invalidateOnRefresh: true,
            },
        });

        // 1. Une équipe : le demi-cercle se dessine, les quinze sièges se posent, le compteur monte jusqu'à 15.
        tl.to(arc, { drawSVG: '100%', duration: 0.5 }, 0)
            .to(
                seats,
                {
                    scale: 1,
                    opacity: 1,
                    duration: 0.5,
                    stagger: { each: 0.045, from: 'edges' },
                    ease: 'back.out(2)',
                },
                0.1,
            )
            .to(state, { n: total, duration: 1, onUpdate: count }, 0.1)
            // 2. L'appel à candidature : une onde part de l'équipe.
            .to(steps[0], { autoAlpha: 0, y: -30, duration: 0.25 }, 1.3)
            .fromTo(steps[1], { autoAlpha: 0, y: 30 }, { autoAlpha: 1, y: 0, duration: 0.25 }, 1.5)
            .to(caps[0], { opacity: 0, duration: 0.2 }, 1.3)
            .to(caps[1], { opacity: 1, duration: 0.2 }, 1.5);
        pulse(tl, 1.5);
        // 3. L'entretien : le VRAE apparaît et se relie à l'équipe.
        tl.to(steps[1], { autoAlpha: 0, y: -30, duration: 0.25 }, 2.7)
            .fromTo(steps[2], { autoAlpha: 0, y: 30 }, { autoAlpha: 1, y: 0, duration: 0.25 }, 2.9)
            .to(caps[1], { opacity: 0, duration: 0.2 }, 2.7)
            .to(caps[2], { opacity: 1, duration: 0.2 }, 2.9)
            .to(vrae, { autoAlpha: 1, y: 0, duration: 0.5, ease: 'power2.out' }, 2.9)
            .to(vlink, { drawSVG: '100%', duration: 0.5 }, 3.2);

        return () => {
            root.classList.remove('is-pinned');
            gsap.set([...steps, ...seats, ...rings, ...caps, arc, vrae, vlink], { clearProps: 'all' });
            state.n = total;
            count();
        };
    });

    // Petit écran : les étapes montent une à une ; le demi-cercle se pose quand il apparaît, puis le VRAE le rejoint.
    mm.add('(max-width: 1023px)', () => {
        steps.forEach((step) =>
            gsap.from(step, {
                y: 40,
                opacity: 0,
                duration: 0.9,
                ease: 'power3.out',
                scrollTrigger: { trigger: step, start: 'top 85%', once: true },
            }),
        );

        gsap.set(seats, { scale: 0, opacity: 0, transformOrigin: '50% 50%' });
        gsap.set(arc, { drawSVG: '0%' });
        gsap.set(vrae, { autoAlpha: 0, y: -24 });
        gsap.set(vlink, { drawSVG: '0%' });
        state.n = 0;
        count();

        const tl = gsap.timeline({ scrollTrigger: { trigger: hemi, start: 'top 80%', once: true } });
        tl.to(arc, { drawSVG: '100%', duration: 0.6, ease: 'power2.inOut' }, 0)
            .to(
                seats,
                {
                    scale: 1,
                    opacity: 1,
                    duration: 0.5,
                    stagger: { each: 0.05, from: 'edges' },
                    ease: 'back.out(2)',
                },
                0.1,
            )
            .to(state, { n: total, duration: 1.1, ease: 'power1.out', onUpdate: count }, 0.1);
        pulse(tl, 1.2);
        tl.to(vrae, { autoAlpha: 1, y: 0, duration: 0.5, ease: 'power2.out' }, 1.5).to(
            vlink,
            { drawSVG: '100%', duration: 0.5, ease: 'power2.inOut' },
            1.8,
        );

        return () => gsap.set([...seats, ...rings, arc, vrae, vlink], { clearProps: 'all' });
    });
}

/* ───────── 5. Son engagement ───────── */
function engagement() {
    const plan = $('[data-sa-plan]');
    if (plan) {
        const vrae = $('[data-sa-lane="vrae"]', plan);
        const others = $('[data-sa-lane="others"]', plan);
        const vraeBars = $$('[data-sa-bar="vrae"]', plan);
        const otherBars = $$('[data-sa-bar="other"]', plan);
        // Au départ, les autres activités occupent le haut de la planification : les deux blocs échangent leurs places.
        const gap = others.offsetTop - vrae.offsetTop - vrae.offsetHeight;
        const swap = () => ({ vrae: others.offsetHeight + gap, others: -(vrae.offsetHeight + gap) });

        gsap.set(otherBars, { scaleX: 0, transformOrigin: 'left center' });
        gsap.set(vraeBars, { scaleX: 0, transformOrigin: 'left center' });
        gsap.set(vrae, { y: () => swap().vrae });
        gsap.set(others, { y: () => swap().others });

        gsap.timeline({ scrollTrigger: { trigger: plan, start: 'top 72%', once: true } })
            .to(otherBars, { scaleX: 1, duration: 0.6, stagger: 0.07, ease: 'power3.out' })
            .to(vraeBars, { scaleX: 1, duration: 0.6, stagger: 0.1, ease: 'power3.out' }, '>-0.2')
            // Les projets du VRAE passent en tête ; les autres activités leur laissent la place.
            .to([vrae, others], { y: 0, duration: 1.2, ease: 'power3.inOut' }, '>0.35')
            .fromTo(
                vraeBars,
                { filter: 'brightness(1)' },
                {
                    filter: 'brightness(1.18)',
                    duration: 0.45,
                    stagger: 0.1,
                    yoyo: true,
                    repeat: 1,
                    ease: 'power1.inOut',
                    clearProps: 'filter',
                },
                '>-0.1',
            );
    }

    const principles = $$('[data-sa-principle]');
    if (principles.length) {
        ScrollTrigger.batch(principles, {
            start: 'top 90%',
            once: true,
            onEnter: (batch) =>
                gsap.from(batch, {
                    y: 50,
                    opacity: 0,
                    duration: 0.9,
                    stagger: 0.14,
                    ease: 'power3.out',
                    clearProps: 'transform,opacity',
                }),
        });
    }
}
