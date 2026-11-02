// Actualités : liste et page d'un article (voir resources/views/news/index.blade.php et show.blade.php).
//
//  Liste    1. La une      : un titre de journal qui se lève lettre à lettre.
//           2. Le ruban    : les derniers titres défilent en boucle ; plus on fait défiler la page vite, plus il accélère et s'incline.
//           3. Le fil      : l'article à la une et les cartes se dévoilent (filet qui se trace, photo par un rideau puis en parallaxe) ;
//                            un curseur « Lire » accompagne la souris ; les cartes d'un filtre ou d'une autre page arrivent de la même façon.
//           4. La suite    : les mots de la phrase arrivent tour à tour.
//  Article  1. L'ouverture : la photo respire et glisse, le titre se lève mot à mot, la barre de lecture se remplit au fil du texte.
//           2. Le texte    : paragraphes, intertitres (leur filet se trace), citations et photos (rideau) apparaissent en lisant.
//           3. La suite    : les articles voisins et « à lire aussi » arrivent comme les cartes de la liste.
//  Le bouton « Copier le lien » fonctionne aussi avec « réduire les animations ».
//
// Renforcement progressif : sans JavaScript ou avec « réduire les animations », la page reste complète (voir resources/css/news.css).
import { t } from '../i18n.js';
import { gsap, ScrollTrigger, SplitText, MOTION_OK } from './gsap.js';

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

export function initNews() {
    share();

    const mm = gsap.matchMedia();

    mm.add(MOTION_OK, (context) => {
        const run = () => {
            document.documentElement.classList.add('nws-ready');
            hero();
            ticker();
            cards(document);
            cursors();
            progress();
            prose();
            pager();
            more();
            ScrollTrigger.refresh();
        };

        // Un filtre ou une page suivante remplace la zone du fil : ses nouvelles cartes se dévoilent à leur tour.
        const onRegion = (event) => {
            cards(event.detail.region);
            ScrollTrigger.refresh();
        };
        document.addEventListener('uaz:region', onRegion);

        // Les polices doivent être chargées avant de découper le texte en lettres et en mots.
        if (document.fonts?.status === 'loaded') run();
        else document.fonts.ready.then(() => context.add(run));

        return () => document.removeEventListener('uaz:region', onRegion);
    });
}

/* ───────── Partage ───────── */
function share() {
    const button = $('[data-nws-copy]');
    if (!button) return;

    const status = $('[data-nws-copied]');
    const label = button.textContent;
    button.hidden = false;

    button.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(button.dataset.nwsCopy);
        } catch {
            return;
        }
        button.textContent = t('Copié');
        if (status) status.textContent = t('Copié');
        setTimeout(() => {
            button.textContent = label;
            if (status) status.textContent = '';
        }, 2200);
    });
}

/* ───────── 1. La une / l'ouverture ───────── */
function hero() {
    const root = $('[data-nws-hero]');
    if (!root) return;

    const title = $('[data-nws-title]', root);
    const article = root.dataset.news === 'article';
    if (title) {
        SplitText.create(title, {
            type: article ? 'words' : 'words,chars',
            wordsClass: 'word',
            charsClass: 'char',
            autoSplit: true,
            onSplit: (self) =>
                article
                    ? gsap.from(self.words, { yPercent: 60, opacity: 0, duration: 1, stagger: 0.05, ease: 'power4.out' })
                    : gsap.from(self.chars, { yPercent: 110, opacity: 0, duration: 1, stagger: 0.03, ease: 'power4.out' }),
        });
    }
    gsap.from($$('[data-nws-fade]', root), { autoAlpha: 0, y: 22, duration: 1, delay: 0.5, stagger: 0.12, ease: 'power3.out' });

    // Page d'un article : la photo se pose, puis glisse plus lentement que la page.
    const cover = $('[data-nws-cover]', root);
    if (cover) {
        gsap.from(cover, { scale: 1.2, duration: 2.4, ease: 'power3.out' });
        gsap.to(cover, { yPercent: 14, scale: 1.08, ease: 'none', scrollTrigger: { trigger: root, start: 'top top', end: 'bottom top', scrub: true } });
    }
}

/* ───────── 2. Le ruban ───────── */
function ticker() {
    const root = $('[data-nws-ticker]');
    const track = $('[data-nws-ticker-track]', root ?? document);
    if (!root || !track) return;

    // Deux copies identiques côte à côte : décaler de la moitié boucle sans coupure.
    const loop = gsap.to(track, { xPercent: -50, ease: 'none', duration: 40, repeat: -1 });
    const skew = gsap.quickTo(track, 'skewX', { duration: 0.4, ease: 'power3.out' });
    const speed = { value: 1 };

    ScrollTrigger.create({
        trigger: root,
        start: 'top bottom',
        end: 'bottom top',
        onUpdate: (self) => {
            const velocity = gsap.utils.clamp(-3000, 3000, self.getVelocity());
            gsap.to(speed, { value: 1 + Math.abs(velocity) / 400, duration: 0.2, overwrite: true, onUpdate: () => loop.timeScale(speed.value * (self.direction || 1)) });
            gsap.to(speed, { value: 1, duration: 1.2, delay: 0.25, overwrite: 'auto' });
            skew(gsap.utils.clamp(-8, 8, -velocity / 400));
        },
    });
    ScrollTrigger.addEventListener('scrollEnd', () => skew(0));
}

/* ───────── 3. Le fil : les cartes ───────── */
function cards(scope) {
    $$('[data-nws-card]', scope).forEach((card) => {
        if (card.dataset.nwsDone) return;
        card.dataset.nwsDone = '1';

        const media = $('[data-nws-media]', card);
        const img = $('[data-nws-img]', card);
        const body = $('.nws-card__body, .nws-lead__body', card);
        const once = { trigger: card, start: 'top 88%', once: true };

        // Le filet du haut se trace, la photo se dévoile par un rideau qui descend, puis le texte monte.
        gsap.fromTo(card, { '--line': 0 }, { '--line': 1, duration: 1, ease: 'power3.inOut', scrollTrigger: once });
        if (media) gsap.fromTo(media, { clipPath: 'inset(0% 0% 100% 0%)' }, { clipPath: 'inset(0% 0% 0% 0%)', duration: 1.2, delay: 0.1, ease: 'power4.inOut', scrollTrigger: once, onComplete: () => gsap.set(media, { clearProps: 'clipPath' }) });
        if (body) gsap.from(body, { y: 36, opacity: 0, duration: 1, delay: 0.35, ease: 'power3.out', scrollTrigger: once });

        // La photo glisse dans son cadre.
        if (img) gsap.fromTo(img, { scale: 1.2, yPercent: -6 }, { scale: 1.2, yPercent: 6, ease: 'none', scrollTrigger: { trigger: card, start: 'top bottom', end: 'bottom top', scrub: true } });
    });
}

/** Curseur « Lire » au-dessus des cartes : uniquement avec une vraie souris. */
function cursors() {
    if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;

    $$('[data-nws-cursor]').forEach((dot) => {
        const root = dot.parentElement;
        const targets = '[data-nws-link], .nws-card__media, .nws-lead__media';

        root.classList.add('is-fine');
        gsap.set(dot, { scale: 0 });
        const x = gsap.quickTo(dot, 'x', { duration: 0.35, ease: 'power3.out' });
        const y = gsap.quickTo(dot, 'y', { duration: 0.35, ease: 'power3.out' });

        root.addEventListener('pointermove', (event) => {
            x(event.clientX);
            y(event.clientY);
        });
        root.addEventListener('pointerover', (event) => {
            if (event.target.closest(targets)) gsap.to(dot, { scale: 1, opacity: 1, duration: 0.35, ease: 'back.out(2)', overwrite: 'auto' });
        });
        root.addEventListener('pointerout', (event) => {
            if (event.target.closest(targets) && !event.relatedTarget?.closest?.(targets)) gsap.to(dot, { scale: 0, opacity: 0, duration: 0.25, overwrite: 'auto' });
        });
    });
}

/* ───────── Article : la barre de lecture ───────── */
function progress() {
    const bar = $('[data-nws-progress] i');
    const article = $('[data-nws-article]');
    if (!bar || !article) return;

    gsap.fromTo(bar, { scaleX: 0 }, { scaleX: 1, ease: 'none', scrollTrigger: { trigger: article, start: 'top 20%', end: 'bottom 75%', scrub: 0.3 } });
}

/* ───────── Article : le texte ───────── */
function prose() {
    const root = $('[data-nws-prose] .prose');
    if (!root) return;

    const blocks = $$(':scope > p, :scope > ul, :scope > ol, :scope > h3, :scope > blockquote, :scope > table', root);
    ScrollTrigger.batch(blocks, {
        start: 'top 92%',
        once: true,
        onEnter: (batch) => gsap.from(batch, { y: 26, opacity: 0, duration: 0.9, stagger: 0.08, ease: 'power3.out', clearProps: 'transform,opacity' }),
    });

    // Intertitres : le texte monte, le filet de rouille se trace.
    $$(':scope > h2', root).forEach((heading) => {
        const trigger = { trigger: heading, start: 'top 90%', once: true };
        gsap.from(heading, { y: 30, opacity: 0, duration: 0.9, ease: 'power3.out', scrollTrigger: trigger, clearProps: 'transform,opacity' });
        gsap.fromTo(heading, { '--rule': 0 }, { '--rule': 1, duration: 1.1, delay: 0.3, ease: 'power3.inOut', scrollTrigger: trigger });
    });

    // Photos du texte : un rideau qui se lève, puis la photo se pose.
    $$('img', root).forEach((img) => {
        const frame = img.closest('figure') ?? img.closest('p') ?? img;
        const trigger = { trigger: frame, start: 'top 88%', once: true };
        gsap.fromTo(frame, { clipPath: 'inset(100% 0% 0% 0%)' }, { clipPath: 'inset(0% 0% 0% 0%)', duration: 1.2, ease: 'power4.inOut', scrollTrigger: trigger, onComplete: () => gsap.set(frame, { clearProps: 'clipPath' }) });
        gsap.from(img, { scale: 1.2, duration: 1.6, ease: 'power3.out', scrollTrigger: trigger });
    });
}

/* ───────── Article : d'un article à l'autre ───────── */
function pager() {
    $$('[data-nws-pager-link]').forEach((link, i) => {
        const img = $('img', link);
        gsap.from(link, { opacity: 0, y: 60, duration: 1, delay: i * 0.12, ease: 'power3.out', scrollTrigger: { trigger: link, start: 'top 92%', once: true } });
        if (img) gsap.fromTo(img, { yPercent: -8, scale: 1.2 }, { yPercent: 8, scale: 1.2, ease: 'none', scrollTrigger: { trigger: link, start: 'top bottom', end: 'bottom top', scrub: true } });
    });
}

/* ───────── 4. La suite ───────── */
function more() {
    const root = $('[data-nws-more]');
    const text = $('[data-nws-more-text]', root ?? document);
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
