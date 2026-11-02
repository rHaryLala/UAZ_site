// Page « Soutenir l'UAZ » dans un vrai navigateur : titre intact, scène épinglée (phrase qui s'allume, mur qui monte), planches des projets
// (budget compté puis restitué tel quel, plan dessiné), barres de montants, mobile, mouvement réduit, anglais.
// Prérequis : serveur lancé, base de développement (deux projets). Usage : node tests/e2e/support.mjs [dossier-captures]
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
const open = async (options, url = '/soutenir') => {
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
const lit = (page) => page.locator('[data-sup-quote] .word').evaluateAll((els) => els.filter((el) => Number(getComputedStyle(el).opacity) > 0.5).length);
const bricksShown = (page) => page.locator('[data-sup-brick]').evaluateAll((els) => els.filter((el) => Number(getComputedStyle(el).opacity) > 0.9 && el.getBoundingClientRect().height > 4).length);

try {
    // ── Ordinateur ──
    const page = await open({ viewport: { width: 1440, height: 900 } });
    check(await until(async () => page.evaluate(() => document.documentElement.classList.contains('sup-ready'))), 'le mode immersif s\'active (sup-ready)');
    check(await until(async () => (await page.locator('[data-sup-title] .char').count()) >= 8), 'le titre se découpe en lettres');
    check(await page.locator('[data-sup-title] .word').evaluateAll((els) => els.every((el) => el.getBoundingClientRect().height < 1.4 * parseFloat(getComputedStyle(el).fontSize))), 'aucun mot du titre n\'est coupé en deux lignes');
    if (out) await page.screenshot({ path: path.join(out, 'soutenir-hero.png') });

    // Scène épinglée : phrase + mur.
    await scrollTo(page, '[data-sup-statement]');
    await wait(500);
    const words = await page.locator('[data-sup-quote] .word').count();
    const litStart = await lit(page);
    const bricksStart = await bricksShown(page);
    check(words > 20 && litStart < words * 0.4, `phrase : la plupart des mots sont éteints à l'arrivée (${litStart}/${words})`);
    check(bricksStart < 20, `mur : presque aucune brique à l'arrivée (${bricksStart}/69)`);
    check(await page.locator('[data-sup-statement]').evaluate((el) => el.parentElement?.classList.contains('pin-spacer') ?? false), 'la scène est épinglée sur grand écran');
    const start = await page.evaluate(() => scrollY);
    await page.evaluate((v) => window.scrollTo(0, v), start + 800);
    await wait(900);
    check((await lit(page)) > litStart && (await bricksShown(page)) > bricksStart, `en défilant : plus de mots allumés (${await lit(page)}) et plus de briques (${await bricksShown(page)})`);
    await page.evaluate((v) => window.scrollTo(0, v), start + 2500);
    await wait(1000);
    check((await lit(page)) === words, 'à la fin : tous les mots sont allumés');
    check((await bricksShown(page)) === 69, `à la fin : le mur est complet (${await bricksShown(page)}/69)`);

    // Planches des projets.
    await scrollTo(page, '[data-sup-sheet]', -100);
    check(await until(async () => page.locator('[data-sup-sheet]').first().evaluate((el) => Number(getComputedStyle(el).opacity) > 0.95)), 'projets : la première planche apparaît');
    check(await until(async () => (await page.locator('[data-sup-amount]').first().innerText()).replace(/\s/g, ' ').trim() === '275 000 €', 8000), 'projets : le budget compté est restitué tel quel (« 275 000 € »)');
    check(await until(async () => page.locator('[data-sup-plan] path').first().evaluate((p) => parseFloat(getComputedStyle(p).strokeDashoffset) < 2 || getComputedStyle(p).strokeDasharray === 'none')), 'projets : le plan se dessine en entier');
    if (out) await page.screenshot({ path: path.join(out, 'soutenir-projets.png') });

    // Barres : proportionnelles et visibles à la fin.
    await scrollTo(page, '[data-sup-bars]', -300);
    check(await until(async () => page.locator('[data-sup-fill]').evaluateAll((els) => els.every((el) => el.getBoundingClientRect().width > 50))), 'montants : les barres se déploient');
    // Les barres se déploient l'une après l'autre : on attend que la plus courte ait fini.
    await until(async () => {
        const w = await page.locator('[data-sup-fill]').evaluateAll((els) => els.map((el) => el.getBoundingClientRect().width));

        return w.length === 2 && Math.abs(w[1] / w[0] - 160 / 275) < 0.015;
    }, 6000);
    const widths = await page.locator('[data-sup-fill]').evaluateAll((els) => els.map((el) => el.getBoundingClientRect().width));
    check(widths.length === 2 && Math.abs(widths[1] / widths[0] - 160 / 275) < 0.03, `montants : la barre de 160 000 vaut ${(widths[1] / widths[0] * 100).toFixed(0)} % de celle de 275 000`);
    check(!(await page.locator('main').innerText()).replace(/\s/g, ' ').includes('435 000'), 'aucune somme totale n\'est inventée');
    await page.context().close();

    // ── Mobile ──
    const mobile = await open({ viewport: { width: 390, height: 844 } });
    await until(async () => mobile.evaluate(() => document.documentElement.classList.contains('sup-ready')));
    check((await mobile.evaluate(() => document.documentElement.scrollWidth)) <= 390, 'mobile : aucun défilement horizontal');
    check(!(await mobile.locator('[data-sup-statement]').evaluate((el) => el.parentElement?.classList.contains('pin-spacer'))), 'mobile : la scène n\'est pas épinglée');
    await scrollTo(mobile, '[data-sup-statement]', 250);
    await wait(900);
    check((await lit(mobile)) > 0 && (await bricksShown(mobile)) > 0, `mobile : phrase (${await lit(mobile)}) et mur (${await bricksShown(mobile)}) avancent au défilement`);
    await mobile.context().close();

    // ── Mouvement réduit ──
    const calm = await open({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
    await wait(1200);
    check(await calm.locator('[data-sup-title]').evaluate((el) => Number(getComputedStyle(el).opacity) === 1 && el.querySelectorAll('.char').length === 0), 'mouvement réduit : le titre est visible, non découpé');
    check((await lit(calm)) === 0 && (await calm.locator('[data-sup-quote]').evaluate((el) => Number(getComputedStyle(el).opacity) === 1)), 'mouvement réduit : la phrase est entièrement lisible');
    check((await bricksShown(calm)) === 69, 'mouvement réduit : le mur est complet');
    check((await calm.locator('.pin-spacer').count()) === 0, 'mouvement réduit : aucune scène épinglée');
    check(await calm.locator('[data-sup-sheet]').first().evaluate((el) => Number(getComputedStyle(el).opacity) === 1), 'mouvement réduit : les planches sont visibles');
    check((await calm.locator('[data-sup-amount]').first().innerText()).replace(/\s/g, ' ').trim() === '275 000 €', 'mouvement réduit : le budget est affiché tel quel');
    await calm.context().close();

    // ── Anglais ──
    const en = await open({ viewport: { width: 1440, height: 900 } }, '/en/soutenir');
    check(await en.getAttribute('html', 'lang') === 'en', 'anglais : <html lang="en">');
    await scrollTo(en, '[data-sup-sheet]', -100);
    check(await until(async () => (await en.locator('[data-sup-amount]').first().innerText()).trim() === '€275,000', 8000), 'anglais : le budget compté est restitué « €275,000 »');
    await en.context().close();

    check(errors.length === 0, `aucune erreur JavaScript${errors.length ? ' : ' + errors.join('; ') : ''}`);
} catch (error) {
    console.log(`✗ parcours interrompu : ${error.message.split('\n')[0]}`);
    failures++;
} finally {
    await browser.close();
}

process.exit(failures ? 1 : 0);
