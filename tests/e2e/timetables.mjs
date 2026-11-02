// Page « Emplois du temps » dans un vrai navigateur : titre intact, tableau qui se compose, ligne de temps, fiches qui défilent sur le côté
// (progression, pastilles, clavier), mobile, mouvement réduit, anglais.
// Prérequis : serveur lancé, base de développement (six PDF dans la page). Usage : node tests/e2e/timetables.mjs [dossier-captures]
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
const until = async (fn, ms = 20000) => {
    const end = Date.now() + ms;
    while (Date.now() < end) {
        try { if (await fn()) return true; } catch { /* page en cours de chargement */ }
        await new Promise((r) => setTimeout(r, 200));
    }
    return false;
};
const wait = (ms) => new Promise((r) => setTimeout(r, ms));

const browser = await chromium.launch();
const errors = [];
const open = async (options, url = '/emplois-du-temps') => {
    const context = await browser.newContext({ locale: 'fr-FR', ...options });
    await context.addInitScript(() => localStorage.setItem('uaz.newsletter', JSON.stringify({ subscribed: true })));
    const page = await context.newPage();
    page.on('pageerror', (e) => !/ViewTransition|Transition was aborted/.test(e.message) && errors.push(e.message.slice(0, 140)));
    await page.goto(base + url, { waitUntil: 'load', timeout: 60000 });

    return page;
};
const scrollTo = async (page, selector, offset = 0) => {
    const top = await page.locator(selector).first().evaluate((el) => el.getBoundingClientRect().top + window.scrollY);
    for (let y = await page.evaluate(() => scrollY); y < top + offset; y += 400) {
        await page.evaluate((v) => window.scrollTo(0, v), y);
        await wait(50);
    }
    await page.evaluate((v) => window.scrollTo(0, v), top + offset);
};
const counter = (page) => page.locator('[data-tt-count]').innerText().then((t) => t.trim());
const headerH = (page) => page.evaluate(() => document.querySelector('.site-header').offsetHeight);

try {
    // ── Ordinateur ──
    const page = await open({ viewport: { width: 1440, height: 900 } });
    check(await until(async () => page.evaluate(() => document.documentElement.classList.contains('tt-ready'))), 'le mode immersif s\'active (tt-ready)');
    check(await until(async () => (await page.locator('[data-tt-title] .char').count()) >= 10), 'le titre se découpe en lettres');
    check(await page.locator('[data-tt-title] .word').evaluateAll((els) => els.every((el) => el.getBoundingClientRect().height < 1.4 * parseFloat(getComputedStyle(el).fontSize))), 'aucun mot du titre n\'est coupé en deux lignes');
    await wait(2500);
    check(await page.locator('[data-tt-block]').evaluateAll((els) => els.length > 10 && els.every((el) => Number(getComputedStyle(el).opacity) > 0.95)), 'le tableau est composé : tous les blocs sont posés');
    check(await until(async () => (await page.locator('[data-tt-block].is-live').count()) >= 1, 9000), 'la ligne de temps allume au moins un bloc');
    if (out) await page.screenshot({ path: path.join(out, 'emplois-hero.png') });

    // Les fiches défilent sur le côté pendant que la page est arrêtée.
    const cards = await page.locator('[data-tt-card]').count();
    check(cards >= 2, `une fiche par PDF (${cards})`);
    check(await page.locator('[data-tt-sheets]').evaluate((el) => el.classList.contains('is-pinned') && el.parentElement?.classList.contains('pin-spacer')), 'les fiches sont épinglées sur grand écran');
    await scrollTo(page, '[data-tt-sheets]', -(await headerH(page)));
    await wait(1200);
    const first = await page.locator('[data-tt-card]').first().evaluate((el) => el.getBoundingClientRect().left);
    check((await counter(page)).startsWith('01 /'), 'compteur : première fiche');
    const start = await page.evaluate(() => scrollY);
    await page.evaluate((v) => window.scrollTo(0, v), start + 700);
    await wait(1500);
    const moved = await page.locator('[data-tt-card]').first().evaluate((el) => el.getBoundingClientRect().left);
    check(moved < first - 200, `en défilant, les fiches glissent sur le côté (${Math.round(first)} → ${Math.round(moved)})`);
    check(await page.locator('[data-tt-bar]').evaluate((el) => new DOMMatrix(getComputedStyle(el).transform).a) > 0.1, 'la barre de progression avance');

    // Une pastille amène à la fiche voulue.
    await page.locator('[data-tt-jump]').last().click();
    check(await until(async () => (await counter(page)).startsWith(String(cards).padStart(2, '0')), 8000), `pastille : on arrive à la dernière fiche (${await counter(page)})`);
    check(await page.locator('[data-tt-jump]').last().getAttribute('aria-current') === 'true', 'pastille : la fiche courante est signalée (aria-current)');
    check(await until(async () => page.locator('[data-tt-card]').last().evaluate((el) => { const r = el.getBoundingClientRect(); return r.left >= 0 && r.right <= innerWidth + 1; }), 8000), 'la dernière fiche est entièrement visible');
    if (out) await page.screenshot({ path: path.join(out, 'emplois-fiches.png') });

    // Les liens restent de vrais liens PDF.
    const link = page.locator('[data-tt-card]').last().locator('a.tt-open');
    check(/\.pdf$/.test(await link.getAttribute('href')) && (await link.getAttribute('target')) === '_blank', 'le lien ouvre le PDF dans un nouvel onglet');

    // Clavier : atteindre une fiche hors champ fait défiler jusqu'à elle.
    await page.evaluate(() => window.scrollTo(0, 0));
    await wait(800);
    await scrollTo(page, '[data-tt-sheets]', -(await headerH(page)));
    await wait(1000);
    await page.locator('[data-tt-card]').nth(cards - 2).locator('a.tt-open').focus();
    check(await until(async () => page.locator('[data-tt-card]').nth(cards - 2).evaluate((el) => { const r = el.getBoundingClientRect(); return r.left >= -1 && r.right <= innerWidth + 1; }), 8000), 'clavier : la fiche focalisée est amenée dans l\'écran');
    await page.context().close();

    // ── Mobile ──
    const mobile = await open({ viewport: { width: 390, height: 844 } });
    await until(async () => mobile.evaluate(() => document.documentElement.classList.contains('tt-ready')));
    check((await mobile.evaluate(() => document.documentElement.scrollWidth)) <= 390, 'mobile : aucun défilement horizontal');
    check(!(await mobile.locator('[data-tt-sheets]').evaluate((el) => el.classList.contains('is-pinned') || el.parentElement?.classList.contains('pin-spacer'))), 'mobile : les fiches ne sont pas épinglées (empilées)');
    await scrollTo(mobile, '[data-tt-card]', -150);
    check(await until(async () => mobile.locator('[data-tt-card]').first().evaluate((el) => Number(getComputedStyle(el).opacity) > 0.95)), 'mobile : les fiches apparaissent au défilement');
    if (out) await mobile.screenshot({ path: path.join(out, 'emplois-mobile.png') });
    await mobile.context().close();

    // ── Mouvement réduit ──
    const calm = await open({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
    await wait(1200);
    check(await calm.locator('[data-tt-title]').evaluate((el) => Number(getComputedStyle(el).opacity) === 1 && el.querySelectorAll('.char').length === 0), 'mouvement réduit : le titre est visible, non découpé');
    check(await calm.locator('[data-tt-block]').evaluateAll((els) => els.every((el) => Number(getComputedStyle(el).opacity) === 1)), 'mouvement réduit : le tableau est complet');
    check((await calm.locator('.pin-spacer').count()) === 0, 'mouvement réduit : rien n\'est épinglé');
    check(await calm.locator('[data-tt-art-block]').evaluateAll((els) => els.length > 0 && els.every((el) => getComputedStyle(el).transform === 'none')), 'mouvement réduit : les motifs des fiches sont dessinés');
    check(await calm.locator('[data-tt-card] a.tt-open').first().isVisible(), 'mouvement réduit : les liens sont visibles');
    await calm.context().close();

    // ── Anglais ──
    const en = await open({ viewport: { width: 1440, height: 900 } }, '/en/emplois-du-temps');
    check(await en.getAttribute('html', 'lang') === 'en', 'anglais : <html lang="en">');
    check((await en.locator('[data-tt-card] a.tt-open').first().innerText()).includes('Open the timetable'), 'anglais : « Open the timetable »');
    await en.context().close();

    check(errors.length === 0, `aucune erreur JavaScript${errors.length ? ' : ' + errors.join('; ') : ''}`);
} catch (error) {
    console.log(`✗ parcours interrompu : ${error.message.split('\n')[0]}`);
    failures++;
} finally {
    await browser.close();
}

process.exit(failures ? 1 : 0);
