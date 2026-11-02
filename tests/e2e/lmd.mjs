// Page « Système LMD » dans un vrai navigateur : titre intact, lettres L M D qui suivent le pointeur et s'allument, montée épinglée (marqueur qui gravit
// l'escalier, jauge de crédits qui se remplit, compteur 0 → 180 → 300, texte de chaque grade), mentions, M1 → M2, Doctorat, menu Ressources, mobile,
// mouvement réduit, anglais. Prérequis : serveur lancé, base de développement. Usage : node tests/e2e/lmd.mjs [dossier-captures]
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
const until = async (fn, ms = 15000) => {
    const end = Date.now() + ms;
    while (Date.now() < end) {
        try { if (await fn()) return true; } catch { /* page en cours de chargement */ }
        await new Promise((r) => setTimeout(r, 150));
    }
    return false;
};
const wait = (ms) => new Promise((r) => setTimeout(r, ms));

const browser = await chromium.launch();
const errors = [];
const open = async (options, url = '/systeme-lmd') => {
    const context = await browser.newContext({ locale: 'fr-FR', ...options });
    await context.addInitScript(() => localStorage.setItem('uaz.newsletter', JSON.stringify({ subscribed: true })));
    const page = await context.newPage();
    page.on('pageerror', (e) => !/ViewTransition|Transition was aborted/.test(e.message) && errors.push(e.message.slice(0, 140)));
    await page.goto(base + url, { waitUntil: 'load', timeout: 90000 });

    return page;
};
const scrollTo = async (page, y) => {
    for (let at = await page.evaluate(() => scrollY); at < y; at += 450) {
        await page.evaluate((v) => window.scrollTo(0, v), at);
        await wait(35);
    }
    await page.evaluate((v) => window.scrollTo(0, v), y);
};
const top = (page, selector) => page.locator(selector).first().evaluate((el) => el.getBoundingClientRect().top + window.scrollY);
const header = (page) => page.evaluate(() => document.querySelector('.site-header').offsetHeight);
const count = (page) => page.locator('[data-lmd-count]').evaluate((el) => Number(el.textContent));
const shown = (page) => page.locator('[data-lmd-step]').evaluateAll((els) => els.map((el) => Number(getComputedStyle(el).opacity) > 0.9));

try {
    // ── Ordinateur ──
    const page = await open({ viewport: { width: 1440, height: 900 } });
    check(await until(async () => page.evaluate(() => document.documentElement.classList.contains('lmd-ready'))), 'le mode immersif s\'active (lmd-ready)');
    check(await until(async () => (await page.locator('[data-lmd-title] .char').count()) >= 8), 'le titre se découpe en lettres');
    check(await page.locator('[data-lmd-title] .word').evaluateAll((els) => els.every((el) => el.getBoundingClientRect().height < 1.4 * parseFloat(getComputedStyle(el).fontSize))), 'aucun mot du titre n\'est coupé en deux lignes');
    await wait(2200);
    check(await page.locator('[data-lmd-letter]').count() === 3, 'trois lettres : L, M, D');

    // Les lettres suivent le pointeur et s'allument au survol.
    const big = page.locator('.lmd-letter__big').nth(1);
    const x0 = (await big.boundingBox()).x;
    await page.mouse.move(1300, 780, { steps: 8 });
    await wait(1200);
    check(Math.abs((await big.boundingBox()).x - x0) > 2, 'les lettres suivent le pointeur');
    await page.locator('[data-lmd-letter]').nth(1).hover();
    check(await until(async () => page.locator('.lmd-letter__big').nth(1).evaluate((el) => getComputedStyle(el).webkitTextStrokeColor === 'rgb(12, 18, 51)'), 2500), 'au survol, la lettre s\'allume (carte dorée)');
    if (out) await page.screenshot({ path: path.join(out, 'lmd-lettres.png') });
    await page.mouse.move(5, 5);

    // La montée : épinglée, le compteur grimpe.
    const stage = await top(page, '[data-lmd-stage]');
    await scrollTo(page, stage - (await header(page)));
    await wait(1200);
    check(await page.locator('[data-lmd-stage]').evaluate((el) => el.parentElement?.classList.contains('pin-spacer')), 'la montée est épinglée sur grand écran');
    check((await count(page)) === 0 && (await shown(page)).join() === 'true,false,false', 'au départ : 0 crédit, seule la Licence est lisible');
    const start = await page.evaluate(() => scrollY);
    const marker0 = await page.locator('[data-lmd-marker]').evaluate((el) => Number(el.getAttribute('cx')));
    await scrollTo(page, start + 560);
    check(await until(async () => (await count(page)) >= 150 && (await shown(page))[0], 4000), `la Licence : le compteur monte vers 180 (${await count(page)})`);
    await scrollTo(page, start + 1250);
    check(await until(async () => (await shown(page)).join() === 'false,true,false', 4000), 'le Master prend la place de la Licence');
    check(await until(async () => (await count(page)) > 200 && Number(await page.locator('[data-lmd-marker]').evaluate((el) => el.getAttribute('cx'))) > marker0 + 100, 4000), 'le Master : le compteur dépasse 200 et le marqueur monte l\'escalier');
    if (out) await page.screenshot({ path: path.join(out, 'lmd-montee.png') });
    await scrollTo(page, start + 2400);
    check(await until(async () => (await count(page)) === 300 && (await shown(page)).join() === 'false,false,true', 5000), 'le Doctorat : 300 crédits atteints, la marche suivante s\'affiche sans chiffre');
    check(await page.locator('[data-lmd-step]').nth(2).evaluate((el) => !/ECTS|\d+ ans/.test(el.textContent)), 'la marche du Doctorat ne porte ni durée ni crédits');
    check(await until(async () => page.locator('[data-lmd-next]').evaluate((el) => getComputedStyle(el).strokeDasharray !== 'none'), 3000), 'le prolongement en pointillés de l\'escalier se dessine');

    // Mentions.
    await scrollTo(page, (await top(page, '#licence')) + 120);
    await wait(2000);
    check(await page.locator('#licence [data-lmd-mention]').evaluateAll((els) => els.length === 7 && els.every((el) => Number(getComputedStyle(el).opacity) > 0.95)), 'Licence : les sept mentions sont posées');
    await page.locator('#licence [data-lmd-mention]').first().hover();
    check(await until(async () => page.locator('#licence [data-lmd-mention]').first().evaluate((el) => getComputedStyle(el).backgroundColor === 'rgb(19, 26, 63)'), 2000), 'Licence : une mention s\'assombrit au survol');
    check((await page.locator('#licence [data-lmd-mention] a').first().getAttribute('href')).includes('/formations/'), 'Licence : la mention renvoie à sa formation');
    await page.mouse.move(5, 5);
    await scrollTo(page, (await top(page, '#master')) + 120);
    await wait(2800);
    check(await page.locator('[data-lmd-stage-item]').evaluateAll((els) => els.length === 2 && els.every((el) => Number(getComputedStyle(el).opacity) > 0.95)), 'Master : M1 et M2 sont là');
    check(await page.locator('[data-lmd-stage-item]').nth(1).evaluate((el) => getComputedStyle(el).getPropertyValue('--line').trim() === '1'), 'Master : le trait pointillé entre M1 et M2 est tracé');
    check(await page.locator('#master [data-lmd-mention]').count() === 3, 'Master : trois mentions');
    await scrollTo(page, (await top(page, '#doctorat')) - 100);
    check(await until(async () => (await page.locator('[data-lmd-doc-text] .word').count()) >= 5, 3000), 'Doctorat : la phrase arrive mot à mot');
    await page.context().close();

    // ── Menu ──
    const menu = await open({ viewport: { width: 1440, height: 900 } }, '/');
    await menu.locator('header button', { hasText: 'Ressources' }).first().hover();
    check(await until(async () => menu.locator('header a', { hasText: 'Système LMD' }).first().isVisible(), 3000), 'le menu Ressources propose « Système LMD »');
    await menu.locator('header a', { hasText: 'Système LMD' }).first().click();
    check(await until(async () => menu.url().endsWith('/systeme-lmd'), 8000), 'le lien mène à la page Système LMD');
    await menu.context().close();

    // ── Mobile ──
    const mobile = await open({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    await wait(2500);
    const height = await mobile.evaluate(() => document.documentElement.scrollHeight);
    for (let y = 0; y < height; y += 400) { await mobile.evaluate((v) => scrollTo(0, v), y); await wait(30); }
    await wait(2500);
    check((await mobile.evaluate(() => document.documentElement.scrollWidth)) <= 390, 'mobile : aucun défilement horizontal');
    check((await mobile.locator('.pin-spacer').count()) === 0, 'mobile : rien n\'est épinglé');
    check(await count(mobile) === 300 && (await shown(mobile)).every(Boolean), 'mobile : les trois grades sont lisibles et le compteur finit à 300');
    if (out) { await mobile.evaluate(() => document.querySelector('[data-lmd-climb]').scrollIntoView()); await wait(1500); await mobile.screenshot({ path: path.join(out, 'lmd-mobile.png') }); }
    await mobile.context().close();

    // ── Mouvement réduit ──
    const calm = await open({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
    await wait(1200);
    check(await calm.locator('[data-lmd-title] .char').count() === 0, 'mouvement réduit : le titre n\'est pas découpé');
    check((await calm.locator('.pin-spacer').count()) === 0, 'mouvement réduit : rien n\'est épinglé');
    check(await count(calm) === 300 && (await shown(calm)).every(Boolean), 'mouvement réduit : les trois grades sont lisibles, l\'escalier est complet (300)');
    check(await calm.locator('#licence [data-lmd-mention]').evaluateAll((els) => els.length === 7 && els.every((el) => Number(getComputedStyle(el).opacity) === 1)), 'mouvement réduit : les mentions sont visibles');
    await calm.context().close();

    // ── Anglais ──
    const en = await open({ viewport: { width: 1440, height: 900 } }, '/en/systeme-lmd');
    check(await en.getAttribute('html', 'lang') === 'en', 'anglais : <html lang="en">');
    check((await en.locator('.lmd-letter__name').first().innerText()).includes('Licence') && (await en.locator('.lmd-letter__grade').first().innerText()).toLowerCase() === 'bac+3', 'anglais : lettres et grades');
    check((await en.locator('.lmd-step__title').first().innerText()).includes('Bac+3'), 'anglais : la marche de la Licence');
    await en.context().close();

    check(errors.length === 0, `aucune erreur JavaScript${errors.length ? ' : ' + errors.join('; ') : ''}`);
} catch (error) {
    console.log(`✗ parcours interrompu : ${error.message.split('\n')[0]}`);
    failures++;
} finally {
    await browser.close();
}

process.exit(failures ? 1 : 0);
