// Page « Bibliothèque Paul Pichot » : une visite en six scènes (voir resources/views/pages/library.blade.php).
//
//  1. Entrer     : vidéo de la visite en drone, lue au rythme du défilement (écran épinglé, 22 secondes).
//  2. Citation   : les mots de la citation s'allument au fil du défilement (SplitText) ; des « livres » dérivent en parallaxe.
//  3. Le lieu    : panorama horizontal de la salle, épinglé, avec trois cartes qui apparaissent à leur passage (containerAnimation).
//  4. Rayonnage  : cinq livres cliquables ; le livre choisi se détache, sa fiche apparaît (clavier : flèches).
//  5. Horaires   : état « ouvert maintenant » à l'heure de Madagascar, ligne du jour, onglets ; l'affiche s'incline à la souris.
//  6. Bienvenue  : titre lettre à lettre, cadre qui se dévoile.
//
// Tout est du renforcement progressif : sans JavaScript, avec « réduire les animations » ou en économiseur de données,
// la page reste complète (les classes `lib-live` / `is-live` n'existent alors pas, voir resources/css/library.css).
import { gsap, ScrollTrigger, SplitText, MOTION_OK } from './gsap.js';
import { isFrench, t } from '../i18n.js';

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
            tilt();
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

/* ───────── 1. Entrer : la vidéo avance quand on défile ───────── */
function intro() {
    const root = $('[data-lib-intro]');
    if (!root) return;

    const stage = $('[data-lib-stage]', root);
    const video = $('[data-lib-video]', root);
    const shade = $('[data-lib-shade]', root);
    const content = $('[data-lib-content]', root);
    const welcome = $('[data-lib-welcome]', root);

    // Entrée du titre : lignes masquées qui montent, puis le reste en fondu.
    SplitText.create($('[data-lib-title]', root), {
        type: 'lines',
        mask: 'lines',
        autoSplit: true,
        onSplit: (self) => gsap.from(self.lines, { yPercent: 115, duration: 1.2, stagger: 0.14, ease: 'expo.out', delay: 0.1 }),
    });
    gsap.from($$('[data-lib-fade]', root), { autoAlpha: 0, y: 22, duration: 0.9, stagger: 0.12, delay: 0.55, ease: 'power3.out' });

    // Sans vidéo (fichiers absents) : héros fixe avec la photo de la salle, pas de scène épinglée.
    if (!video) return;

    root.classList.add('lib-live');
    const scrub = createVideoScrub(video);

    const chars = SplitText.create($('[data-lib-welcome-text]', welcome), { type: 'words,chars', wordsClass: 'word', charsClass: 'char' }).chars;
    const tl = gsap.timeline({
        defaults: { ease: 'none' },
        scrollTrigger: {
            trigger: stage,
            start: () => `top top+=${headerHeight()}`,
            // Environ quatre écrans de défilement pour les 22 secondes : la vidéo avance à la vitesse du doigt ou de la molette.
            end: () => `+=${Math.round(window.innerHeight * 4)}`,
            pin: true,
            scrub: true,
            anticipatePin: 1,
            invalidateOnRefresh: true,
            onUpdate: (self) => scrub.seek(self.progress),
            onRefresh: (self) => scrub.seek(self.progress),
        },
    });

    // Le titre s'efface dès que la visite commence ; le voile s'assombrit pour la phrase finale.
    tl.to(content, { autoAlpha: 0, y: -70, duration: 0.1 }, 0);
    tl.to(shade, { opacity: 0.08, duration: 0.1 }, 0);
    tl.to(shade, { opacity: 0.5, duration: 0.14 }, 0.82);
    tl.from(chars, { yPercent: 130, opacity: 0, rotate: 7, stagger: 0.012, duration: 0.1, ease: 'power3.out' }, 0.86);
    tl.to({}, { duration: 1 - 0.96 }, 0.96);
}

/**
 * Lecture pilotée par le défilement : la position de la vidéo suit celle de la page, avec un lissage pour éviter les à-coups.
 * La vidéo est d'abord téléchargée une fois en mémoire (blob) : le déplacement est alors instantané dans les deux sens et ne dépend
 * ni du serveur (requêtes par plages d'octets) ni du réseau. En attendant, l'affiche (première image) reste affichée.
 * Version allégée sur petit écran ou connexion limitée. Chaque image de la vidéo est une image clé (encodage « intra ») : se placer
 * sur n'importe quelle image est immédiat, dans les deux sens, ce qui garde le défilement fluide.
 */
function createVideoScrub(video) {
    const connection = navigator.connection;
    const slow = Boolean(connection?.saveData) || ['slow-2g', '2g', '3g'].includes(connection?.effectiveType);
    // Trois niveaux : mobile / connexion limitée (≈ 3 Mo), tablette (≈ 13 Mo), ordinateur haute définition (≈ 19 Mo).
    const light = window.innerWidth < 768 || slow;
    const wide = !light && video.dataset.srcUhd && window.innerWidth * (window.devicePixelRatio || 1) >= 1300;
    const url = light ? video.dataset.srcSd : wide ? video.dataset.srcUhd : video.dataset.srcHd;
    let target = 0;
    let shown = 0;
    let ready = false;
    let objectUrl = null;
    let destroyed = false;

    video.muted = true;
    video.addEventListener('loadedmetadata', () => {
        ready = true;
        // Certains navigateurs mobiles n'autorisent le déplacement qu'après un premier « play ».
        video.play().then(() => video.pause()).catch(() => {});
    });

    fetch(url)
        .then((response) => (response.ok ? response.blob() : Promise.reject(new Error('vidéo indisponible'))))
        .then((blob) => {
            if (destroyed) return;
            objectUrl = URL.createObjectURL(blob);
            video.src = objectUrl;
        })
        .catch(() => {
            // Échec : l'affiche reste en place, la page reste utilisable.
        });

    const FRAME = 1 / 24;
    let last = -1;
    const tick = (time, deltaTime) => {
        if (!ready || !video.duration) return;
        // Lissage indépendant de la fréquence d'écran : la vidéo rattrape le défilement en ~0,2 s, sans à-coups.
        shown += (target - shown) * (1 - Math.exp(-(deltaTime / 1000) * 14));
        // On se cale sur une image précise : pas de position « entre deux images » à décoder, et pas de demande répétée pour la même.
        const frame = Math.round((Math.min(shown, 0.999) * video.duration) / FRAME);
        if (frame === last) return;
        last = frame;
        video.currentTime = frame * FRAME;
    };
    gsap.ticker.add(tick);

    return {
        seek: (progress) => {
            target = progress;
        },
        destroy: () => {
            destroyed = true;
            gsap.ticker.remove(tick);
            if (objectUrl) URL.revokeObjectURL(objectUrl);
        },
    };
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

    if (!isFrench()) return `${h}:${String(m).padStart(2, '0')}`;

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

    if (slot) return { state: 'open', text: t(':status · jusqu\'à :time', { status: t(subject.open), time: formatMinutes(slot[1]) }) };

    const later = today?.find(([start]) => start > minutes);
    if (later) return { state: 'closed', text: t(':status · reprend à :time', { status: t(subject.closed), time: formatMinutes(later[0]) }) };

    for (let offset = 1; offset <= 7; offset++) {
        const nextDay = (day + offset) % 7;
        const slots = schedule[nextDay];
        if (slots?.length) {
            const when = offset === 1 ? t('demain') : t(DAYS[nextDay]);

            return {
                state: today === undefined ? 'unknown' : 'closed',
                text: t(':status · :verb :when à :time', {
                    status: today === undefined ? t('Horaires du :day non communiqués', { day: t(DAYS[day]) }) : t(subject.closed),
                    verb: t(subject.reopens),
                    when,
                    time: formatMinutes(slots[0][0]),
                }),
            };
        }
    }

    return { state: 'unknown', text: t('Horaires indisponibles') };
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
        statusText.textContent = t(':status (heure de Madagascar)', { status: result.text });
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

/* Affiche des horaires et fenêtre du site : elles s'inclinent en suivant la souris (écrans à souris seulement). */
function tilt() {
    if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;

    $$('[data-lib-tilt]').forEach((link) => {
        const sheet = $('[data-lib-tilt-sheet]', link);
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

/* ───────── 6. Bienvenue chez vous ───────── */
function contact() {
    const root = $('[data-lib-contact]');
    if (!root) return;

    const big = $('[data-lib-big]', root);
    if (big) {
        SplitText.create(big, {
            type: 'words,chars',
            wordsClass: 'word',
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
}
