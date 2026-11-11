// Icônes de réseaux sociaux de l'en-tête des lettres d'information : des PNG (les messageries n'affichent pas le SVG), en blanc pour un en-tête
// foncé et en bleu nuit pour un en-tête clair, à 2 × leur taille d'affichage (28 px). À relancer seulement pour changer le dessin des icônes :
//   node scripts/build-newsletter-icons.mjs        → public/images/newsletter/{reseau}-{white|dark}.png
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const out = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', 'public', 'images', 'newsletter');
fs.mkdirSync(out, { recursive: true });

// Tracés sur une grille de 24 × 24 (pleins, sauf Instagram et le lien, dessinés au trait).
const ICONS = {
    facebook: { fill: 'M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z' },
    youtube: { fill: 'M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z' },
    linkedin: { fill: 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 2.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z' },
    tiktok: { fill: 'M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z' },
    instagram: { stroke: '<rect x="2.5" y="2.5" width="19" height="19" rx="5.5"/><circle cx="12" cy="12" r="4.3"/><circle cx="17.6" cy="6.4" r="1.1" fill="currentColor" stroke="none"/>', width: 2 },
    website: { stroke: '<path d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/>', width: 1.9 },
};
const COLORS = { white: '#ffffff', dark: '#1d2233' };

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 56, height: 56 }, deviceScaleFactor: 1 });
for (const [name, icon] of Object.entries(ICONS)) {
    for (const [variant, color] of Object.entries(COLORS)) {
        const body = icon.fill
            ? `<path d="${icon.fill}" fill="currentColor"/>`
            : `<g fill="none" stroke="currentColor" stroke-width="${icon.width}" stroke-linecap="round" stroke-linejoin="round">${icon.stroke}</g>`;
        await page.setContent(`<body style="margin:0;background:transparent;color:${color}"><svg viewBox="0 0 24 24" width="56" height="56" xmlns="http://www.w3.org/2000/svg" style="display:block">${body}</svg></body>`);
        await page.screenshot({ path: path.join(out, `${name}-${variant}.png`), omitBackground: true, clip: { x: 0, y: 0, width: 56, height: 56 } });
    }
}
await browser.close();
console.log('icônes écrites dans', out);
