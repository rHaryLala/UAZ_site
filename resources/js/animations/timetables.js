// Page « Emplois du temps » : quatre scènes (voir resources/views/pages/timetables.blade.php).
//
//  1. La semaine     : le titre se lève lettre à lettre, le tableau se compose bloc après bloc, une ligne de temps le parcourt et allume les blocs
//                      qu'elle traverse ; le tableau suit le pointeur (inclinaison) et glisse au défilement.
//  2. Le ruban       : les noms des mentions défilent en boucle ; plus on fait défiler la page vite, plus il accélère et s'incline.
//  3. Les fiches     : sur grand écran la page s'arrête et les fiches défilent sur le côté (une barre de progression, des pastilles pour aller
//                      à une mention) ; chaque fiche pivote en arrivant et dessine son motif. Sur petit écran : les fiches montent une à une.
//  4. L'invitation   : les mots de la phrase arrivent tour à tour, la ligne pointillée avance.
//
// Renforcement progressif : sans JavaScript ou avec « réduire les animations », la page reste complète (voir resources/css/timetables.css).
import { gsap, ScrollTrigger, SplitText, MOTION_OK } from './gsap.js';

const headerHeight = () => document.querySelector('.site-header')?.offsetHeight ?? 0;
const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

export function initTimetables() {
    const mm = gsap.matchMedia();

    mm.add(MOTION_OK, (context) => {
        const run = () => {
            document.documentElement.classList.add('tt-ready');
            hero();
            ticker();
            sheets();
            invite();
            ScrollTrigger.refresh();
        };

        // Les polices doivent être chargées avant de découper le texte en lettres et en mots.
        if (document.fonts?.status === 'loaded') run();
        else document.fonts.ready.then(() => context.add(run));
    });
}

/* ───────── 1. La semaine ───────── */
function hero() {
    const root = $('[data-tt-hero]');
    if (!root) return;

    SplitText.create($('[data-tt-title]', root), {
        type: 'words,chars',
        wordsClass: 'word',
        charsClass: 'char',
        autoSplit: true,
        onSplit: (self) => gsap.from(self.chars, { yPercent: 110, opacity: 0, duration: 1, stagger: 0.035, ease: 'power4.out' }),
    });
    gsap.from($$('[data-tt-fade]', root), { autoAlpha: 0, y: 22, duration: 1, delay: 0.6, stagger: 0.12, ease: 'power3.out' });

    const board = $('[data-tt-board]', root);
    if (!board) return;
    const blocks = $$('[data-tt-block]', board);

    // Le tableau se compose : colonnes tracées, puis blocs qui tombent à leur place dans le désordre.
    gsap.set(board, { rotation: -2.5, transformPerspective: 1400 });
    gsap.from(board, { autoAlpha: 0, y: 50, rotation: -8, duration: 1.2, ease: 'power3.out' });
    gsap.from($$('[data-tt-col]', board), { scaleY: 0, duration: 0.9, stagger: 0.08, ease: 'power3.out', delay: 0.2 });
    gsap.from(blocks, { autoAlpha: 0, scale: 0.4, y: -30, rotation: () => gsap.utils.random(-8, 8), duration: 0.8, stagger: { each: 0.06, from: 'random' }, ease: 'back.out(1.8)', delay: 0.5 });

    // La ligne de temps parcourt le tableau de haut en bas, en boucle, et allume chaque bloc qu'elle traverse.
    const now = $('[data-tt-now]', board);
    if (now) {
        const light = () => {
            const y = now.getBoundingClientRect().top;
            blocks.forEach((block) => {
                const rect = block.getBoundingClientRect();
                block.classList.toggle('is-live', rect.top <= y && y <= rect.bottom);
            });
        };
        gsap.fromTo(now, { top: '5%' }, { top: '95%', duration: 7, ease: 'none', repeat: -1, repeatDelay: 0.6, delay: 1.6, onUpdate: light });
    }

    // Inclinaison suivant le pointeur (souris uniquement).
    if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        const tiltX = gsap.quickTo(board, 'rotationX', { duration: 0.8, ease: 'power3.out' });
        const tiltY = gsap.quickTo(board, 'rotationY', { duration: 0.8, ease: 'power3.out' });
        root.addEventListener('pointermove', (event) => {
            const rect = root.getBoundingClientRect();
            tiltY(((event.clientX - rect.left) / rect.width - 0.5) * 14);
            tiltX(-((event.clientY - rect.top) / rect.height - 0.5) * 10);
        });
        root.addEventListener('pointerleave', () => {
            tiltX(0);
            tiltY(0);
        });
    }

    // Au défilement, le tableau glisse plus lentement que la page.
    gsap.to(board, { yPercent: -10, ease: 'none', scrollTrigger: { trigger: root, start: 'top top', end: 'bottom top', scrub: true } });
}

/* ───────── 2. Le ruban ───────── */
function ticker() {
    const root = $('[data-tt-ticker]');
    const track = $('[data-tt-ticker-track]', root ?? document);
    if (!root || !track) return;

    // Deux copies identiques côte à côte : décaler de la moitié boucle sans coupure.
    const loop = gsap.to(track, { xPercent: -50, ease: 'none', duration: 28, repeat: -1 });
    const skew = gsap.quickTo(track, 'skewX', { duration: 0.4, ease: 'power3.out' });
    const speed = { value: 1 };

    ScrollTrigger.create({
        trigger: root,
        start: 'top bottom',
        end: 'bottom top',
        onUpdate: (self) => {
            const velocity = gsap.utils.clamp(-3000, 3000, self.getVelocity());
            // La boucle accélère avec le défilement et suit son sens ; elle revient doucement à sa vitesse de croisière.
            gsap.to(speed, { value: 1 + Math.abs(velocity) / 400, duration: 0.2, overwrite: true, onUpdate: () => loop.timeScale(speed.value * (self.direction || 1)) });
            gsap.to(speed, { value: 1, duration: 1.2, delay: 0.25, overwrite: 'auto' });
            skew(gsap.utils.clamp(-8, 8, -velocity / 400));
        },
    });
    ScrollTrigger.addEventListener('scrollEnd', () => skew(0));
}

/* ───────── 3. Les fiches ───────── */
function sheets() {
    const root = $('[data-tt-sheets]');
    const track = $('[data-tt-track]', root ?? document);
    const cards = $$('[data-tt-card]', root ?? document);
    if (!root || !track || !cards.length) return;

    const total = cards.length;
    const bar = $('[data-tt-bar]', root);
    const count = $('[data-tt-count]', root);
    const name = $('[data-tt-name]', root);
    const jumps = $$('[data-tt-jump]', root);

    // Le motif de chaque fiche se dessine quand elle arrive : ses blocs se déplient l'un après l'autre.
    const drawings = new Map(
        cards.map((card) => {
            const blocks = $$('[data-tt-art-block]', card);
            gsap.set(blocks, { scaleY: 0 });

            return [card, gsap.to(blocks, { scaleY: 1, duration: 0.7, stagger: { each: 0.035, from: 'start' }, ease: 'back.out(1.6)', paused: true })];
        }),
    );

    const mm = gsap.matchMedia();

    // Grand écran : la page s'arrête, les fiches défilent sur le côté.
    mm.add('(min-width: 1024px)', () => {
        root.classList.add('is-pinned');
        const distance = () => Math.max(0, track.scrollWidth - window.innerWidth);
        let active = -1;

        const show = (index) => {
            if (index === active) return;
            active = index;
            cards.forEach((card, i) => card.classList.toggle('is-active', i === index));
            jumps.forEach((jump, i) => {
                jump.classList.toggle('is-active', i === index);
                if (i === index) jump.setAttribute('aria-current', 'true');
                else jump.removeAttribute('aria-current');
            });
            if (count) count.textContent = `${String(index + 1).padStart(2, '0')} / ${String(total).padStart(2, '0')}`;
            if (name) name.textContent = cards[index].querySelector('.tt-card__title')?.textContent ?? '';
        };
        show(0);

        const slide = gsap.to(track, {
            x: () => -distance(),
            ease: 'none',
            scrollTrigger: {
                trigger: root,
                start: () => `top top+=${headerHeight()}`,
                end: () => `+=${distance()}`,
                pin: true,
                scrub: 0.8,
                anticipatePin: 1,
                invalidateOnRefresh: true,
                onUpdate: (self) => {
                    if (bar) gsap.set(bar, { scaleX: self.progress });
                    show(Math.round(self.progress * (total - 1)));
                },
            },
        });
        const trigger = slide.scrollTrigger;

        cards.forEach((card) => {
            const sheet = $('[data-tt-sheet]', card);
            // Chaque fiche pivote en entrant par la droite, se pose de face au centre, puis s'éloigne en pivotant de l'autre côté.
            gsap.timeline({ defaults: { ease: 'none' }, scrollTrigger: { trigger: card, containerAnimation: slide, start: 'left right', end: 'right left', scrub: true } })
                .fromTo(sheet, { rotationY: 16, scale: 0.92, transformPerspective: 1000 }, { rotationY: 0, scale: 1, duration: 1 })
                .to(sheet, { rotationY: -16, scale: 0.92, duration: 1 });
            ScrollTrigger.create({ trigger: card, containerAnimation: slide, start: 'left 85%', once: true, onEnter: () => drawings.get(card).play() });
        });

        // Une pastille, ou le clavier qui atteint une fiche hors champ : la page défile jusqu'à elle.
        const goTo = (index) => {
            const y = trigger.start + (trigger.end - trigger.start) * (index / Math.max(1, total - 1));
            if (window.__lenis) window.__lenis.scrollTo(y, { duration: 1.4 });
            else window.scrollTo({ top: y, behavior: 'smooth' });
        };
        const onJump = (event) => {
            event.preventDefault();
            event.stopPropagation(); // Lenis (ancres) viserait sinon la fiche elle-même, pas sa position dans le défilement latéral
            goTo(Number(event.currentTarget.dataset.ttJump));
        };
        const onFocus = (event) => {
            const rect = event.currentTarget.getBoundingClientRect();
            if (rect.left < 0 || rect.right > window.innerWidth) goTo(cards.indexOf(event.currentTarget));
        };
        jumps.forEach((jump) => jump.addEventListener('click', onJump));
        cards.forEach((card) => card.addEventListener('focusin', onFocus));

        return () => {
            root.classList.remove('is-pinned');
            jumps.forEach((jump) => jump.removeEventListener('click', onJump));
            cards.forEach((card) => card.removeEventListener('focusin', onFocus));
            cards.forEach((card) => card.classList.remove('is-active'));
            drawings.forEach((tl) => tl.progress(1));
        };
    });

    // Petit écran : les fiches montent une à une.
    mm.add('(max-width: 1023px)', () => {
        cards.forEach((card) => {
            gsap.from(card, { autoAlpha: 0, y: 50, duration: 0.9, ease: 'power3.out', scrollTrigger: { trigger: card, start: 'top 88%', once: true } });
            ScrollTrigger.create({ trigger: card, start: 'top 82%', once: true, onEnter: () => drawings.get(card).play() });
        });
    });
}

/* ───────── 4. L'invitation ───────── */
function invite() {
    const root = $('[data-tt-invite]');
    const text = $('[data-tt-invite-text]', root ?? document);
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
                stagger: 0.1,
                ease: 'power3.out',
                scrollTrigger: { trigger: root, start: 'top 70%', once: true },
            }),
    });

    // Les pointillés de la ligne avancent sans fin, comme le temps qui passe.
    const line = $('[data-tt-line]', root);
    if (line) gsap.to(line, { strokeDashoffset: -160, duration: 8, ease: 'none', repeat: -1 });
}
