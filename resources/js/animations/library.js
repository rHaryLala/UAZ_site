// Page « Bibliothèque Paul Pichot » : une visite en six scènes (voir resources/views/pages/library.blade.php).
//
//  1. Entrer     : l'enseigne se rapproche (zoom), la porte s'ouvre en iris sur la salle de lecture (défilement épinglé, scrub).
//  2. Citation   : les mots de la citation s'allument au fil du défilement (SplitText) ; des « livres » dérivent en parallaxe.
//  3. Le lieu    : panorama horizontal de la salle, épinglé, avec trois cartes qui apparaissent à leur passage (containerAnimation).
//  4. Rayonnage  : cinq livres cliquables ; le livre choisi se détache, sa fiche apparaît (clavier : flèches).
//  5. Horaires   : état « ouvert maintenant » à l'heure de Madagascar, ligne du jour, onglets ; l'affiche s'incline à la souris.
//  6. Bienvenue  : titre lettre à lettre, cadre qui se dévoile.
//
// Tout est du renforcement progressif : sans JavaScript, avec « réduire les animations » ou en économiseur de données,
// la page reste complète (les classes `lib-live` / `is-live` n'existent alors pas, voir resources/css/library.css).
import { gsap, ScrollTrigger, SplitText, MOTION_OK } from './gsap.js';

const DESKTOP_MOTION = '(min-width: 1024px) and (prefers-reduced-motion: no-preference)';
const headerHeight = () => document.querySelector('.site-header')?.offsetHeight ?? 0;
const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

export function initLibrary() {
    // Les horaires et le rayonnage fonctionnent aussi sans animation (mouvement réduit).
    initHours();
    initShelf();

    const mm = gsap.matchMedia();

    mm.add(MOTION_OK, (context) => {
        const run = () => {
            document.documentElement.classList.add('lib-ready');
            intro();
            quote();
            contact();
            poster();
            ScrollTrigger.refresh();
        };

        // Les polices doivent être chargées avant de découper le texte en lignes, mots et lettres.
        if (document.fonts?.status === 'loaded') run();
        else document.fonts.ready.then(() => context.add(run));
    });

    mm.add(DESKTOP_MOTION, () => panorama());

    mm.add('(max-width: 1023px) and (prefers-reduced-motion: no-preference)', () => {
        // Petit écran : les cartes du lieu apparaissent simplement en défilant.
        ScrollTrigger.batch('[data-lib-card]', {
            start: 'top 88%',
            once: true,
            onEnter: (cards) => gsap.from(cards, { autoAlpha: 0, y: 36, stagger: 0.12, duration: 0.9, ease: 'expo.out' }),
        });
    });
}

/* ───────── 1. Entrer ───────── */
function intro() {
    const root = $('[data-lib-intro]');
    if (!root) return;

    const stage = $('[data-lib-stage]', root);
    const outside = $('[data-lib-outside]', root);
    const inside = $('[data-lib-inside]', root);
    const insideImg = $('img', inside ?? root);
    const content = $('[data-lib-content]', root);
    const welcome = $('[data-lib-welcome]', root);

    root.classList.add('lib-live');

    // Entrée du titre : lignes masquées qui montent, puis le reste en fondu.
    SplitText.create($('[data-lib-title]', root), {
        type: 'lines',
        mask: 'lines',
        autoSplit: true,
        onSplit: (self) => gsap.from(self.lines, { yPercent: 115, duration: 1.2, stagger: 0.14, ease: 'expo.out', delay: 0.1 }),
    });
    gsap.from($$('[data-lib-fade]', root), { autoAlpha: 0, y: 22, duration: 0.9, stagger: 0.12, delay: 0.55, ease: 'power3.out' });

    // Scène épinglée : zoom vers la porte, iris, « Bienvenue chez vous ! ».
    const chars = SplitText.create($('[data-lib-welcome-text]', welcome), { type: 'words,chars', wordsClass: 'word', charsClass: 'char' }).chars;
    const tl = gsap.timeline({
        defaults: { ease: 'none' },
        scrollTrigger: {
            trigger: stage,
            start: () => `top top+=${headerHeight()}`,
            end: () => `+=${Math.round(window.innerHeight * 1.5)}`,
            pin: true,
            scrub: 0.7,
            anticipatePin: 1,
            invalidateOnRefresh: true,
        },
    });

    tl.to(content, { autoAlpha: 0, y: -80, duration: 0.3 }, 0);
    if (outside) tl.to(outside, { scale: 2.1, ease: 'power2.in', duration: 1 }, 0);
    if (inside) {
        tl.fromTo(inside, { clipPath: 'circle(0% at 50% 86%)' }, { clipPath: 'circle(145% at 50% 86%)', ease: 'power2.inOut', duration: 0.75 }, 0.3);
        tl.fromTo(insideImg, { scale: 1.4 }, { scale: 1, duration: 0.8 }, 0.3);
    }
    tl.from(chars, { yPercent: 130, opacity: 0, rotate: 7, stagger: 0.035, duration: 0.45, ease: 'power3.out' }, 0.85);
    tl.to({}, { duration: 0.4 });
}

/* ───────── 2. La citation ───────── */
function quote() {
    const section = $('[data-lib-quote-section]');
    const text = $('[data-lib-quote]');
    if (!section || !text) return;

    SplitText.create(text, {
        type: 'words',
        wordsClass: 'word',
        autoSplit: true,
        onSplit: (self) =>
            gsap.fromTo(
                self.words,
                { opacity: 0.14 },
                {
                    opacity: 1,
                    ease: 'none',
                    stagger: 0.12,
                    scrollTrigger: { trigger: text, start: 'top 82%', end: 'bottom 48%', scrub: 0.6 },
                },
            ),
    });

    // Livres décoratifs : ils dérivent à des vitesses différentes (profondeur).
    $$('[data-lib-drift]', section).forEach((book) =>
        gsap.to(book, {
            y: parseFloat(book.dataset.libDrift) || -100,
            ease: 'none',
            scrollTrigger: { trigger: section, start: 'top bottom', end: 'bottom top', scrub: true },
        }),
    );
}

/* ───────── 3. Le lieu : panorama horizontal ───────── */
function panorama() {
    const root = $('[data-lib-pan]');
    const sticky = $('[data-lib-pan-sticky]', root ?? document);
    const track = $('[data-lib-track]', root ?? document);
    if (!root || !sticky || !track) return;

    root.classList.add('is-live');
    const distance = () => Math.max(0, track.offsetWidth - window.innerWidth);

    const pan = gsap.to(track, {
        x: () => -distance(),
        ease: 'none',
        scrollTrigger: {
            trigger: sticky,
            start: () => `top top+=${headerHeight()}`,
            end: () => `+=${Math.round(distance() * 1.15)}`,
            pin: true,
            scrub: 0.8,
            anticipatePin: 1,
            invalidateOnRefresh: true,
        },
    });

    // La photo glisse un peu moins vite que les cartes : effet de profondeur.
    const photo = $('[data-lib-photo] img', track);
    if (photo) gsap.fromTo(photo, { x: () => distance() * 0.12 }, { x: () => -distance() * 0.04, ease: 'none', scrollTrigger: { trigger: sticky, start: 'top top', end: () => `+=${Math.round(distance() * 1.15)}`, scrub: 0.8, invalidateOnRefresh: true } });

    const bar = $('[data-lib-bar]', root);
    if (bar) gsap.to(bar, { scaleX: 1, ease: 'none', scrollTrigger: { trigger: sticky, start: () => `top top+=${headerHeight()}`, end: () => `+=${Math.round(distance() * 1.15)}`, scrub: true, invalidateOnRefresh: true } });

    // Chaque carte surgit quand le panorama l'amène dans le champ.
    $$('[data-lib-card]', track).forEach((card) => {
        gsap.from(card, {
            autoAlpha: 0,
            y: 60,
            rotate: 1.5,
            duration: 0.9,
            ease: 'expo.out',
            scrollTrigger: { trigger: card, containerAnimation: pan, start: 'left 92%', toggleActions: 'play none none reverse' },
        });
    });

    return () => root.classList.remove('is-live');
}

/* ───────── 4. Le rayonnage ───────── */
function initShelf() {
    const root = $('[data-lib-shelf]');
    if (!root) return;

    const books = $$('[data-lib-book]', root);
    const panels = $$('[data-lib-panel]', root);
    if (!books.length || books.length !== panels.length) return;

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    root.classList.add('is-live');

    let current = -1;
    const select = (index, { focus = false } = {}) => {
        if (index === current) return;
        const next = panels[index];

        books.forEach((book, i) => {
            const active = i === index;
            book.setAttribute('aria-pressed', String(active));
            if (reduced) gsap.set(book, { y: active ? -26 : 0 });
            else gsap.to(book, { y: active ? -30 : 0, rotate: active ? -2.5 : 0, duration: 0.7, ease: active ? 'back.out(2.2)' : 'power3.out', overwrite: 'auto' });
        });

        // La fiche visible est décidée par la classe (CSS) ; GSAP n'anime que son contenu.
        panels.forEach((panel) => panel.classList.toggle('is-active', panel === next));
        if (!reduced) {
            gsap.fromTo(
                next.children,
                { autoAlpha: 0, y: 26, x: 18 },
                { autoAlpha: 1, y: 0, x: 0, duration: 0.8, stagger: 0.08, ease: 'expo.out', overwrite: 'auto' },
            );
        }

        current = index;
        if (focus) books[index].focus();
    };

    books.forEach((book, i) => {
        book.addEventListener('click', () => select(i));
        // Un livre se soulève au survol, sans gêner le livre déjà choisi.
        if (!reduced) {
            book.addEventListener('pointerenter', () => i !== current && gsap.to(book, { y: -14, duration: 0.35, ease: 'power2.out', overwrite: 'auto' }));
            book.addEventListener('pointerleave', () => i !== current && gsap.to(book, { y: 0, duration: 0.45, ease: 'power3.out', overwrite: 'auto' }));
        }
        book.addEventListener('keydown', (event) => {
            const step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[event.key];
            if (!step) return;
            event.preventDefault();
            select((i + step + books.length) % books.length, { focus: true });
        });
    });

    select(0);

    // Entrée de l'étagère : les livres montent un à un quand elle arrive à l'écran.
    if (!reduced) {
        gsap.from(books, {
            yPercent: 40,
            autoAlpha: 0,
            duration: 0.9,
            stagger: 0.09,
            ease: 'back.out(1.5)',
            scrollTrigger: { trigger: root, start: 'top 70%', once: true },
        });
    }
}

/* ───────── 5. Horaires ───────── */
const TIMEZONE = 'Indian/Antananarivo'; // Madagascar : UTC+3, sans changement d'heure
const DAYS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];

const toMinutes = (hhmm) => {
    const [h, m] = hhmm.split(':').map(Number);

    return h * 60 + m;
};
const formatMinutes = (minutes) => {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;

    return m ? `${h} h ${String(m).padStart(2, '0')}` : `${h} h`;
};

/** Jour (0 = dimanche) et minute courante à Madagascar, quel que soit le fuseau du visiteur. */
export function madagascarNow(date = new Date()) {
    const parts = Object.fromEntries(
        new Intl.DateTimeFormat('en-US', { timeZone: TIMEZONE, weekday: 'short', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
            .formatToParts(date)
            .map((part) => [part.type, part.value]),
    );

    return { day: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].indexOf(parts.weekday), minutes: Number(parts.hour) * 60 + Number(parts.minute) };
}

/** Lit un tableau d'horaires du DOM : { jour: [[début, fin], …] } (un jour absent = horaires non communiqués). */
export function readSchedule(table) {
    const schedule = {};
    $$('tr[data-days]', table).forEach((row) => {
        const slots = row.dataset.slots ? row.dataset.slots.split(',').map((slot) => slot.split('-').map(toMinutes)) : [];
        row.dataset.days.split(',').forEach((day) => (schedule[Number(day)] = slots));
    });

    return schedule;
}

/** État à un instant donné : { state: open|closed|unknown, text }. */
export function describeStatus(schedule, { day, minutes }, subject) {
    const today = schedule[day];
    const slot = today?.find(([start, end]) => minutes >= start && minutes < end);

    if (slot) return { state: 'open', text: `${subject.open} · jusqu'à ${formatMinutes(slot[1])}` };

    const later = today?.find(([start]) => start > minutes);
    if (later) return { state: 'closed', text: `${subject.closed} · reprend à ${formatMinutes(later[0])}` };

    for (let offset = 1; offset <= 7; offset++) {
        const nextDay = (day + offset) % 7;
        const slots = schedule[nextDay];
        if (slots?.length) {
            const when = offset === 1 ? 'demain' : DAYS[nextDay];

            return {
                state: today === undefined ? 'unknown' : 'closed',
                text: `${today === undefined ? `Horaires du ${DAYS[day]} non communiqués` : subject.closed} · ${subject.reopens} ${when} à ${formatMinutes(slots[0][0])}`,
            };
        }
    }

    return { state: 'unknown', text: 'Horaires indisponibles' };
}

function initHours() {
    const root = $('[data-lib-hours]');
    if (!root) return;

    const tabs = $$('[data-lib-tab]', root);
    const tables = Object.fromEntries($$('[data-lib-table]', root).map((wrap) => [wrap.dataset.libTable, wrap]));
    const status = $('[data-lib-status]', root);
    const statusText = $('[data-lib-status-text]', root);
    const subjects = {
        open: { open: 'La bibliothèque est ouverte', closed: 'La bibliothèque est fermée', reopens: 'ouvre' },
        loan: { open: 'Le prêt et la prolongation sont ouverts', closed: 'Le prêt et la prolongation sont fermés', reopens: 'reprennent' },
    };
    const schedules = Object.fromEntries(Object.entries(tables).map(([key, wrap]) => [key, readSchedule(wrap)]));
    let active = 'open';

    const update = () => {
        const now = madagascarNow();
        $$('tr[data-days]', root).forEach((row) => row.classList.toggle('is-today', row.dataset.days.split(',').map(Number).includes(now.day)));

        const result = describeStatus(schedules[active], now, subjects[active]);
        status.dataset.state = result.state;
        statusText.textContent = `${result.text} (heure de Madagascar)`;
    };

    const show = (key, focus = false) => {
        active = key;
        tabs.forEach((tab) => {
            const selected = tab.dataset.libTab === key;
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
            if (selected && focus) tab.focus();
        });
        Object.entries(tables).forEach(([name, wrap]) => {
            wrap.hidden = name !== key;
            if (name === key && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                gsap.fromTo($$('tr, .lib-note', wrap), { autoAlpha: 0, x: -16 }, { autoAlpha: 1, x: 0, duration: 0.6, stagger: 0.06, ease: 'expo.out', clearProps: 'transform,opacity,visibility' });
            }
        });
        update();
    };

    root.classList.add('is-live');
    tabs.forEach((tab) => {
        tab.addEventListener('click', () => show(tab.dataset.libTab));
        tab.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
            event.preventDefault();
            const next = tabs[(tabs.indexOf(tab) + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length];
            show(next.dataset.libTab, true);
        });
    });

    show('open');
    window.setInterval(update, 60000);
}

/* Affiche : elle s'incline en suivant la souris (écrans à souris seulement). */
function poster() {
    const link = $('[data-lib-poster]');
    const sheet = link && $('.lib-poster__sheet', link);
    if (!sheet || !window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;

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
}

/* ───────── 6. Bienvenue chez vous ───────── */
function contact() {
    const root = $('[data-lib-contact]');
    if (!root) return;

    const big = $('[data-lib-big]', root);
    if (big) {
        SplitText.create(big, {
            type: 'chars',
            charsClass: 'char',
            autoSplit: true,
            onSplit: (self) =>
                gsap.from(self.chars, {
                    yPercent: 110,
                    rotate: 8,
                    opacity: 0,
                    stagger: 0.04,
                    duration: 0.9,
                    ease: 'expo.out',
                    scrollTrigger: { trigger: big, start: 'top 85%', once: true },
                }),
        });
    }

    const frame = $('[data-lib-frame-img]', root);
    if (frame) {
        gsap.fromTo(frame, { clipPath: 'inset(0 100% 0 0)' }, { clipPath: 'inset(0 0% 0 0)', duration: 1.4, ease: 'expo.inOut', scrollTrigger: { trigger: frame, start: 'top 80%', once: true } });
        gsap.fromTo($('img', frame), { scale: 1.3 }, { scale: 1, ease: 'none', scrollTrigger: { trigger: frame, start: 'top bottom', end: 'bottom top', scrub: true } });
    }
}
