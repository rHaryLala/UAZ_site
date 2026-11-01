// Page « Dortoirs » dans un vrai navigateur : chaque scène à sa position de défilement (captures) et les interactions.
// Prérequis : serveur lancé. Usage : node tests/e2e/dormitories.mjs [dossier-captures]
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const url = `${base}/vie-etudiante/dortoirs`;
const out = process.argv[2];
if (out) fs.mkdirSync(out, { recursive: true });
let failures = 0;
const check = (ok, label) => {
    console.log(`${ok ? '✓' : '✗'} ${label}`);
    if (!ok) failures++;
};

const browser = await chromium.launch();
const hideNewsletter = (page) => page.addInitScript(() => localStorage.setItem('uaz.newsletter', JSON.stringify({ subscribed: true })));

async function desktop() {
    const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'fr-FR' })).newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message.slice(0, 140)));
    await hideNewsletter(page);
    await page.goto(url, { waitUntil: 'load' });
    await page.waitForFunction(() => document.documentElement.classList.contains('dor-ready'), null, { timeout: 15000 }).catch(() => {});
    check(await page.evaluate(() => document.documentElement.classList.contains('dor-ready')), 'le mode immersif s\'active (dor-ready)');
    await page.waitForTimeout(2400);
    if (out) await page.screenshot({ path: path.join(out, '1-porte.png') });

    const top = (selector) => page.evaluate((s) => document.querySelector(s).getBoundingClientRect().top + window.scrollY, selector);
    const shot = async (name, y, wait = 1300) => {
        await page.evaluate((top) => (window.__lenis ? window.__lenis.scrollTo(top, { immediate: true }) : window.scrollTo(0, top)), y);
        await page.waitForTimeout(wait);
        if (out) await page.screenshot({ path: path.join(out, name) });
    };

    // La porte s'ouvre : la zone visible de la photo grandit.
    const heroY = await top('[data-dor-stage]');
    const visible = () => page.evaluate(() => { const r = document.querySelector('[data-dor-door]').getBoundingClientRect(); const c = getComputedStyle(document.querySelector('[data-dor-door]')).clipPath; return c; });
    const c0 = await visible();
    await shot('2-porte-mi.png', heroY + 700);
    const c1 = await visible();
    await shot('3-porte-ouverte.png', heroY + 1450);
    const c2 = await visible();
    check(c0 !== c1 && c1 !== c2, `la porte s'ouvre en défilant (${c0.slice(0, 28)} → ${c1.slice(0, 28)} → ${c2.slice(0, 28)})`);

    await shot('4-coeur.png', await top('[data-dor-statement]') + 20, 1800);

    // Résidences : les chiffres montent jusqu'à 80 et 68.
    await shot('5-residences.png', await top('[data-dor-houses]') + 150, 3200);
    const counts = await page.evaluate(() => [...document.querySelectorAll('[data-dor-count]')].map((e) => e.textContent.trim()));
    check(counts.join() === '80,68', `les chiffres atteignent 80 et 68 (${counts})`);

    // Schéma R+2 : 12 cases (8 chambres, réception, salle à manger, 2 chambres du précepteur), étages tracés.
    const planY = await top('[data-dor-plan-stage]');
    const visibleCells = () => page.evaluate(() => [...document.querySelectorAll('[data-dor-cell]')].filter((c) => +getComputedStyle(c).opacity > 0.9).length);
    await shot('6a-plan-debut.png', planY + 100, 1200);
    const n0 = await visibleCells();
    await shot('6b-plan-milieu.png', planY + 800, 1500);
    const n1 = await visibleCells();
    await shot('6c-plan-fin.png', planY + 1800, 1800);
    const n2 = await visibleCells();
    check((await page.locator('[data-dor-cell]').count()) === 12, 'le schéma compte 12 cases (8 chambres, réception, salle à manger, 2 chambres du précepteur)');
    check(n0 < n1 && n1 <= n2 && n2 === 12, `le bâtiment se construit en défilant (${n0} → ${n1} → ${n2} cases visibles)`);
    check((await page.evaluate(() => +getComputedStyle(document.querySelector('[data-dor-vision]')).opacity)) > 0.9, 'la phrase de vision apparaît en fin de scène');
    check((await page.locator('a[href*="/soutenir/un-grand-dortoir-pour-les-garcons"]').count()) === 1, 'le lien vers le projet à soutenir est présent');

    // La nuit : des fenêtres s'allument.
    await shot('7-nuit.png', await top('[data-dor-night]') - 200, 3500);
    const lit = await page.locator('[data-dor-window].is-lit').count();
    check(lit >= 20, `les fenêtres de la silhouette s'allument (${lit})`);
    const hrefs = await page.locator('.dor-link').evaluateAll((els) => els.map((e) => e.getAttribute('href')));
    check(hrefs.length === 2 && hrefs.every((h) => h.startsWith('https://www.facebook.com/')), `les deux liens (Open House, MJA) viennent du texte importé (${hrefs.length})`);
    check(errors.length === 0, `aucune erreur JavaScript${errors.length ? ' : ' + errors.join('; ') : ''}`);
}

async function mobile() {
    const page = await (await browser.newContext({ viewport: { width: 390, height: 800 }, locale: 'fr-FR' })).newPage();
    await hideNewsletter(page);
    await page.goto(url, { waitUntil: 'load' });
    await page.waitForTimeout(3000);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    check(overflow <= 1, `mobile : aucun défilement horizontal (${overflow}px)`);
    check(!(await page.evaluate(() => document.querySelector('[data-dor-plan]').classList.contains('is-live'))), 'mobile : le schéma n\'est pas épinglé');
    if (out) await page.screenshot({ path: path.join(out, 'm1-porte.png') });
    await page.evaluate(() => document.querySelector('[data-dor-building]').scrollIntoView({ block: 'center' }));
    await page.waitForTimeout(3500);
    if (out) await page.screenshot({ path: path.join(out, 'm2-plan.png') });
    const n = await page.evaluate(() => [...document.querySelectorAll('[data-dor-cell]')].filter((c) => +getComputedStyle(c).opacity > 0.9).length);
    check(n === 12, `mobile : le schéma se construit à son arrivée (${n}/12)`);
}

async function reducedMotion() {
    const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce', locale: 'fr-FR' })).newPage();
    await hideNewsletter(page);
    await page.goto(url, { waitUntil: 'load' });
    await page.waitForTimeout(1800);
    check(await page.locator('[data-dor-title]').isVisible(), 'mouvement réduit : le titre est visible');
    check(!(await page.evaluate(() => document.querySelector('[data-dor-hero]').classList.contains('is-live'))), 'mouvement réduit : pas de scène épinglée');
    const hidden = await page.evaluate(() => [...document.querySelectorAll('[data-dor-cell]')].filter((c) => +getComputedStyle(c).opacity < 0.9).length);
    check(hidden === 0, 'mouvement réduit : les 12 cases du schéma sont visibles');
    check((await page.evaluate(() => +getComputedStyle(document.querySelector('[data-dor-count]')).opacity)) === 1 && (await page.locator('[data-dor-count]').first().innerText()) === '80', 'mouvement réduit : les chiffres sont affichés tels quels');
}

try {
    await desktop();
    await mobile();
    await reducedMotion();
} catch (error) {
    console.log(`✗ parcours interrompu : ${error.message.split('\n')[0]}`);
    failures++;
} finally {
    await browser.close();
}

process.exit(failures ? 1 : 0);
