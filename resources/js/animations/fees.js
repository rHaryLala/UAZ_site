// Page « Frais académiques » : le registre et le ticket de caisse (voir resources/views/pages/fees.blade.php).
//
//  1. Le registre : les filets du grand livre de comptes se tracent (DrawSVG), le titre s'écrit mot à mot (SplitText).
//  2. Le relevé   : à l'arrivée, le ticket s'imprime de haut en bas, ses lignes se posent une à une, le total monte jusqu'à son montant.
//                   À chaque changement de parcours (événement « uaz:fees-render » du simulateur, resources/js/fees-sim.js) : lignes qui se
//                   reposent, total qui rattrape son nouveau montant, barre de répartition et comparaison qui se redessinent.
//  3. Le paiement : chaque plan se découpe en parts (50 + 50, 4 × 25…) à son arrivée.
//  4. Les tableaux : le contenu d'un tableau se déplie en douceur.
//
// Le simulateur fonctionne sans ce module (économiseur de données, « réduire les animations ») : seuls les effets disparaissent.
import { DrawSVGPlugin } from 'gsap/DrawSVGPlugin';
import { gsap, ScrollTrigger, SplitText, MOTION_OK } from './gsap.js';
import { formatAr } from '../format-ar.js';

gsap.registerPlugin(DrawSVGPlugin);

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

export function initFees() {
    const mm = gsap.matchMedia();

    mm.add(MOTION_OK, (context) => {
        const run = () => {
            document.documentElement.classList.add('fee-ready');
            hero();
            receipt();
            plans();
            tables();
            ScrollTrigger.refresh();
        };

        // Les polices doivent être chargées avant de découper le titre en mots.
        if (document.fonts?.status === 'loaded') run();
        else document.fonts.ready.then(() => context.add(run));
    });
}

/* ───────── 1. Le registre ───────── */
function hero() {
    const root = $('[data-fee-hero]');
    if (!root) return;

    SplitText.create($('[data-fee-title]', root), {
        type: 'words',
        wordsClass: 'word',
        mask: 'words',
        autoSplit: true,
        onSplit: (self) => gsap.from(self.words, { yPercent: 115, duration: 1.2, stagger: 0.14, ease: 'expo.out', delay: 0.15 }),
    });
    gsap.from($$('[data-fee-fade]', root), { autoAlpha: 0, y: 24, duration: 0.9, stagger: 0.12, delay: 0.5, ease: 'power3.out' });

    // Les filets du registre se tracent, puis restent ; les doubles filets (terracotta) arrivent en dernier.
    const rules = $$('[data-fee-rule]', root);
    gsap.fromTo(rules, { drawSVG: '0%' }, { drawSVG: '100%', duration: 1.6, stagger: 0.09, ease: 'power2.inOut', delay: 0.1 });
}

/* ───────── 2. Le relevé ───────── */
function receipt() {
    const root = $('[data-fee-receipt]');
    const paper = $('[data-fee-paper]', root ?? document);
    if (!root || !paper) return;

    const total = $('[data-fee-total]', root);
    let revealed = false;
    let printed = false;
    let counter = null;
    let lastTotal = 0;

    gsap.set(paper, { clipPath: 'inset(0 0 100% 0)' });

    const lines = () => $$('[data-fee-line]', root);
    const segments = () => $$('[data-fee-seg]', document.querySelector('[data-fee-sim]'));
    const compareBars = () => $$('[data-fee-cmp]', document.querySelector('[data-fee-sim]'));

    const countTo = (to, from = 0, duration = 1.1) => {
        counter?.kill();
        // Le simulateur vient d'écrire le nouveau montant : on revient tout de suite à l'ancien (avant l'affichage de l'image suivante)
        // pour que le décompte parte de là, sans voir le montant final clignoter une image.
        total.textContent = formatAr(from);
        const state = { value: from };
        counter = gsap.to(state, {
            value: to,
            duration,
            ease: 'power2.out',
            onUpdate: () => (total.textContent = formatAr(state.value)),
            onComplete: () => (total.textContent = formatAr(to)),
        });
        lastTotal = to;
    };

    const settle = () => {
        gsap.fromTo(lines(), { autoAlpha: 0, x: -22 }, { autoAlpha: 1, x: 0, duration: 0.55, stagger: 0.05, ease: 'power3.out', clearProps: 'transform,opacity,visibility', overwrite: true });
        gsap.fromTo(segments(), { scaleX: 0 }, { scaleX: 1, duration: 0.9, stagger: 0.07, ease: 'expo.out', clearProps: 'transform', overwrite: true });
        gsap.fromTo(compareBars(), { scaleX: 0 }, { scaleX: 1, duration: 1, stagger: 0.12, ease: 'expo.out', clearProps: 'transform', overwrite: true });
    };

    // Première impression : le papier sort de la fente, ligne après ligne.
    const print = () => {
        printed = true;
        const target = Number(total.dataset.value) || 0;
        gsap.timeline()
            .fromTo(paper, { clipPath: 'inset(0 0 100% 0)', y: -24 }, { clipPath: 'inset(0 0 0% 0)', y: 0, duration: 1.5, ease: 'power2.inOut' }, 0)
            .add(settle, 0.25)
            .add(() => countTo(target, 0, 1.4), 0.3);
    };

    // Changement de parcours : le papier se repose d'un petit rebond, les montants rattrapent leur nouvelle valeur.
    const update = (detail) => {
        gsap.fromTo(paper, { y: -10 }, { y: 0, duration: 0.8, ease: 'elastic.out(1, 0.5)', overwrite: 'auto' });
        settle();
        countTo(detail.total, lastTotal, 0.9);
    };

    window.addEventListener('uaz:fees-render', (event) => {
        if (!revealed) return;
        if (printed) update(event.detail);
        else print();
    });

    ScrollTrigger.create({
        trigger: root,
        start: 'top 78%',
        once: true,
        onEnter: () => {
            revealed = true;
            if (root.dataset.rendered !== undefined) print();
        },
    });
}

/* ───────── 3. Le paiement ───────── */
function plans() {
    $$('[data-fee-plan]').forEach((plan) => {
        const parts = $$('[data-fee-part]', plan);
        gsap.from(parts, { scaleX: 0, duration: 0.9, stagger: 0.14, ease: 'expo.out', scrollTrigger: { trigger: plan, start: 'top 85%', once: true } });
        gsap.from($$('b', plan), { autoAlpha: 0, duration: 0.5, stagger: 0.14, delay: 0.5, scrollTrigger: { trigger: plan, start: 'top 85%', once: true } });
    });
}

/* ───────── 4. Les tableaux ───────── */
function tables() {
    $$('[data-fee-detail]').forEach((detail) => {
        detail.addEventListener('toggle', () => {
            if (detail.open) gsap.from($('.fee-detail__scroll', detail), { autoAlpha: 0, y: -12, duration: 0.6, ease: 'power3.out' });
        });
    });
}
