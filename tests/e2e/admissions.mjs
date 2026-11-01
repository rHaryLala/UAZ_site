// Pages « Admission Licence » et « Admission Master » dans un vrai navigateur : chaque scène à sa position de défilement (captures),
// puis les interactions. Prérequis : serveur lancé. Usage : node tests/e2e/admissions.mjs [dossier-captures]
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const out = process.argv[2];
if (out) fs.mkdirSync(out, { recursive: true });
let failures = 0;
const check = (ok, label) => {
    console.log(`${ok ? '✓' : '✗'} ${label}`);
    if (!ok) failures++;
};

const browser = await chromium.launch();
const hideNewsletter = (page) => page.addInitScript(() => localStorage.setItem('uaz.newsletter', JSON.stringify({ subscribed: true })));

async function desktop(level, expected) {
    const url = `${base}/admissions/${level}`;
    const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'fr-FR' })).newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message.slice(0, 140)));
    await hideNewsletter(page);
    await page.goto(url, { waitUntil: 'load' });
    await page.waitForFunction(() => document.documentElement.classList.contains('adm-ready'), null, { timeout: 15000 }).catch(() => {});
    check(await page.evaluate(() => document.documentElement.classList.contains('adm-ready')), `${level} : le mode immersif s'active`);
    await page.waitForTimeout(2800);
    if (out) await page.screenshot({ path: path.join(out, `${level}-1-ouverture.png`) });

    const top = (selector) => page.evaluate((s) => document.querySelector(s).getBoundingClientRect().top + window.scrollY, selector);
    const shot = async (name, y, wait = 1300) => {
        await page.evaluate((top) => (window.__lenis ? window.__lenis.scrollTo(top, { immediate: true }) : window.scrollTo(0, top)), y);
        await page.waitForTimeout(wait);
        if (out) await page.screenshot({ path: path.join(out, `${level}-${name}`) });
    };

    // Calendrier : le point avance sur le chemin, les dates s'éclairent.
    const calY = await top('[data-adm-cal-stage]');
    const dotX = () => page.evaluate(() => Math.round(document.querySelector('[data-adm-dot]').getBoundingClientRect().left));
    const lit = () => page.evaluate(() => [...document.querySelectorAll('[data-adm-mile]')].filter((m) => +getComputedStyle(m).opacity > 0.95).length);
    await shot('2a-calendrier-debut.png', calY + 60, 1300);
    const x0 = await dotX(); const l0 = await lit();
    await shot('2b-calendrier-milieu.png', calY + 700, 1500);
    const x1 = await dotX(); const l1 = await lit();
    await shot('2c-calendrier-fin.png', calY + 1350, 1500);
    const x2 = await dotX(); const l2 = await lit();
    check(x0 < x1 && x1 < x2, `${level} : le point avance sur le chemin des dates (${x0} → ${x1} → ${x2} px)`);
    check(l0 <= l1 && l1 <= l2 && l2 === expected.milestones, `${level} : les ${expected.milestones} dates s'éclairent à son passage (${l0} → ${l1} → ${l2})`);

    // Dossier : la chemise s'ouvre, les pièces se cochent, le compteur monte, le tampon apparaît.
    const docsY = await top('[data-adm-docs]');
    const counter = () => page.locator('[data-adm-docs-count]').innerText();
    await shot('3a-dossier-debut.png', docsY - 760, 1400);
    const c0 = Number(await counter());
    await shot('3b-dossier-milieu.png', docsY - 330, 1500);
    const c1 = Number(await counter());
    await shot('3c-dossier-fin.png', docsY + (await page.evaluate(() => document.querySelector('[data-adm-docs]').offsetHeight)) - 200, 1800);
    const c2 = Number(await counter());
    check(c0 < c1 && c1 < c2 && c2 === expected.documents, `${level} : le compteur monte jusqu'à ${expected.documents} pièces (${c0} → ${c1} → ${c2})`);
    check((await page.evaluate(() => +getComputedStyle(document.querySelector('[data-adm-stamp]')).opacity)) > 0.9, `${level} : le tampon « Dossier complet » apparaît à la fin`);
    check((await page.locator('.adm-forms__link').count()) === expected.forms, `${level} : ${expected.forms} formulaire(s) à télécharger repris du texte importé`);

    // Mentions : ouvrir une autre carte déplace les autres (Flip).
    const mentionsY = await top('[data-adm-cards]');
    await shot('4a-mentions.png', mentionsY - 200, 1800);
    check((await page.locator('[data-adm-card]').count()) === expected.mentions, `${level} : ${expected.mentions} mentions`);
    check((await page.locator('[data-adm-card].is-open').count()) === 1, `${level} : une seule carte ouverte au départ`);
    await page.locator('[data-adm-toggle]').nth(2).click();
    await page.waitForTimeout(1600);
    check((await page.locator('[data-adm-toggle]').nth(2).getAttribute('aria-expanded')) === 'true' && (await page.locator('[data-adm-card].is-open').count()) === 1, `${level} : un clic ouvre la 3e carte et referme la précédente`);
    if (out) await page.screenshot({ path: path.join(out, `${level}-4b-mention-ouverte.png`) });

    await shot('5-depot.png', await top('[data-adm-contact]') - 60, 1800);
    check(errors.length === 0, `${level} : aucune erreur JavaScript${errors.length ? ' : ' + errors.join('; ') : ''}`);
}

async function mobile(level) {
    const page = await (await browser.newContext({ viewport: { width: 390, height: 800 }, locale: 'fr-FR' })).newPage();
    await hideNewsletter(page);
    await page.goto(`${base}/admissions/${level}`, { waitUntil: 'load' });
    await page.waitForTimeout(3200);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    check(overflow <= 1, `${level} mobile : aucun défilement horizontal (${overflow}px)`);
    check(!(await page.evaluate(() => document.querySelector('[data-adm-calendar]').classList.contains('is-live'))), `${level} mobile : le calendrier n'est pas épinglé`);
    if (out) await page.screenshot({ path: path.join(out, `${level}-m1-ouverture.png`) });
    await page.evaluate(() => document.querySelector('[data-adm-calendar]').scrollIntoView());
    await page.waitForTimeout(2200);
    if (out) await page.screenshot({ path: path.join(out, `${level}-m2-calendrier.png`) });
}

async function reducedMotion(level, expected) {
    const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce', locale: 'fr-FR' })).newPage();
    await hideNewsletter(page);
    await page.goto(`${base}/admissions/${level}`, { waitUntil: 'load' });
    await page.waitForTimeout(1800);
    check(await page.locator('[data-adm-title]').isVisible(), `${level} mouvement réduit : le titre est visible`);
    check(!(await page.evaluate(() => document.querySelector('[data-adm-calendar]').classList.contains('is-live'))), `${level} mouvement réduit : pas de scène épinglée`);
    check((await page.evaluate(() => [...document.querySelectorAll('[data-adm-doc]')].every((d) => +getComputedStyle(d).opacity === 1))), `${level} mouvement réduit : toutes les pièces du dossier sont lisibles`);
    check((await page.evaluate(() => [...document.querySelectorAll('[data-adm-mile]')].every((d) => +getComputedStyle(d).opacity === 1))), `${level} mouvement réduit : toutes les dates sont lisibles`);
    await page.locator('[data-adm-toggle]').nth(1).click();
    await page.waitForTimeout(300);
    check((await page.locator('[data-adm-toggle]').nth(1).getAttribute('aria-expanded')) === 'true', `${level} mouvement réduit : les cartes restent utilisables`);
}

const levels = {
    licence: { milestones: 3, documents: 9, forms: 2, mentions: 8 },
    master: { milestones: 3, documents: 9, forms: 1, mentions: 3 },
};

try {
    for (const [level, expected] of Object.entries(levels)) {
        await desktop(level, expected);
        await mobile(level);
        await reducedMotion(level, expected);
    }
} catch (error) {
    console.log(`✗ parcours interrompu : ${error.message.split('\n')[0]}`);
    failures++;
} finally {
    await browser.close();
}

process.exit(failures ? 1 : 0);
