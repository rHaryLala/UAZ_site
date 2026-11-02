// Texte riche (article d'actualité, description d'événement) : paragraphes, listes, citations, intertitres (leur filet se trace) et photos (rideau)
// apparaissent au fil de la lecture. `root` est l'élément `.prose` qui contient le texte ; rien n'est changé si le texte est vide.
import { gsap, ScrollTrigger } from './gsap.js';

const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

export function revealProse(root) {
    if (!root) return;

    const blocks = $$(':scope > p, :scope > ul, :scope > ol, :scope > h3, :scope > blockquote, :scope > table', root);
    ScrollTrigger.batch(blocks, {
        start: 'top 92%',
        once: true,
        onEnter: (batch) => gsap.from(batch, { y: 26, opacity: 0, duration: 0.9, stagger: 0.08, ease: 'power3.out', clearProps: 'transform,opacity' }),
    });

    // Intertitres : le texte monte, le filet se trace.
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
