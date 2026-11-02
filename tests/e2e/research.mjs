// Page « Centre de recherche » dans un vrai navigateur : réseau du héros, titre intact, phrase épinglée dont les mots s'allument,
// fil tracé, fiches de l'équipe, mobile sans défilement horizontal, mouvement réduit (tout visible, rien d'épinglé), version anglaise.
// Prérequis : serveur lancé, base de développement (équipe du centre). Usage : node tests/e2e/research.mjs [dossier-captures]
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

const browser = await chromium.launch();
const errors = [];
const open = async (options, url = '/centre-de-recherche') => {
    const context = await browser.newContext({ locale: 'fr-FR', ...options });
    // La fenêtre de la lettre d'information s'ouvre à la première visite : on la considère déjà vue.
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
        await new Promise((r) => setTimeout(r, 50));
    }
    await page.evaluate((v) => window.scrollTo(0, v), top + offset);
};

try {
    // ── Ordinateur ──
    const page = await open({ viewport: { width: 1440, height: 900 } });
    check(await until(async () => (await page.evaluate(() => document.documentElement.classList.contains('res-ready')))), 'le mode immersif s\'active (res-ready)');
    check(await until(async () => (await page.locator('[data-res-edge]').count()) > 20), 'le réseau du héros est dessiné (liens et points)');
    check(await until(async () => (await page.locator('[data-res-title] .char').count()) >= 15), 'le titre se découpe en lettres');
    const wrap = await page.locator('[data-res-title] .word').evaluateAll((els) => els.every((el) => el.getBoundingClientRect().height < 1.4 * parseFloat(getComputedStyle(el).fontSize)));
    check(wrap, 'aucun mot du titre n\'est coupé en deux lignes');
    check(await page.locator('[data-res-ring]').count() === 1, 'l\'anneau du sigle est présent');
    if (out) await page.screenshot({ path: path.join(out, 'recherche-hero.png') });

    // Phrase épinglée : les mots s'allument avec le défilement.
    await scrollTo(page, '[data-res-statement]');
    await new Promise((r) => setTimeout(r, 500));
    const opacities = () => page.locator('[data-res-quote] .word').evaluateAll((els) => els.map((el) => Number(getComputedStyle(el).opacity)));
    const before = await opacities();
    check(before.length > 15 && before.filter((o) => o < 0.5).length > before.length * 0.6, `phrase : la plupart des mots sont éteints à l'arrivée (${before.filter((o) => o < 0.5).length}/${before.length})`);
    check(await page.locator('.pin-spacer [data-res-statement], [data-res-statement]').first().evaluate((el) => el.parentElement?.classList.contains('pin-spacer') ?? false), 'phrase : la scène est épinglée sur grand écran');
    const start = await page.evaluate(() => scrollY);
    await page.evaluate((v) => window.scrollTo(0, v), start + 700);
    await new Promise((r) => setTimeout(r, 900));
    const mid = await opacities();
    check(mid.filter((o) => o > 0.5).length > before.filter((o) => o > 0.5).length, `phrase : de plus en plus de mots s'allument en défilant (${mid.filter((o) => o > 0.5).length} allumés)`);
    await page.evaluate((v) => window.scrollTo(0, v), start + 2400);
    await new Promise((r) => setTimeout(r, 900));
    const end = await opacities();
    check(end.every((o) => o > 0.95), 'phrase : tous les mots sont allumés à la fin de la scène');
    const thread = await page.locator('[data-res-thread]').evaluate((el) => getComputedStyle(el).strokeDasharray + '|' + getComputedStyle(el).strokeDashoffset);
    check(parseFloat(thread.split('|')[1]) < 2, `phrase : le fil est tracé en entier (décalage ${thread.split('|')[1]})`);

    // Équipe : fiches visibles, photos pleines.
    await scrollTo(page, '[data-res-people]', 60);
    check(await until(async () => (await page.locator('[data-res-person]').evaluateAll((els) => els.length > 0 && els.every((el) => Number(getComputedStyle(el).opacity) > 0.95)))), 'équipe : les fiches apparaissent');
    const photoOk = await page.locator('[data-res-photo] img').evaluateAll((els) => els.every((img) => img.getBoundingClientRect().height >= img.parentElement.getBoundingClientRect().height - 2));
    check(photoOk, 'équipe : les photos remplissent leur cadre');

    // Domaine : la ligne et les points.
    if (await page.locator('[data-res-chain]').count()) {
        await scrollTo(page, '[data-res-chain]', -300);
        check(await until(async () => (await page.locator('.res-chain__dot').evaluateAll((els) => els.length > 0 && els.every((el) => el.getBoundingClientRect().width > 8)))), 'domaine : les mentions sont reliées');
        check(await until(async () => (await page.locator('.res-chain__link span:last-child').evaluateAll((els) => els.every((el) => Number(getComputedStyle(el).opacity) > 0.95)))), 'domaine : les noms des mentions apparaissent');
    }
    check(!(await page.content()).includes('TODO-CONTENU'), 'le texte d\'attente TODO-CONTENU n\'est jamais affiché');
    if (out) await page.screenshot({ path: path.join(out, 'recherche-domaine.png') });
    await page.context().close();

    // ── Mobile ──
    const mobile = await open({ viewport: { width: 390, height: 844 } });
    await until(async () => mobile.evaluate(() => document.documentElement.classList.contains('res-ready')));
    check((await mobile.evaluate(() => document.documentElement.scrollWidth)) <= 390, 'mobile : aucun défilement horizontal');
    check(!(await mobile.locator('[data-res-statement]').evaluate((el) => el.parentElement?.classList.contains('pin-spacer'))), 'mobile : la phrase n\'est pas épinglée');
    await scrollTo(mobile, '[data-res-statement]', 200);
    await new Promise((r) => setTimeout(r, 800));
    const lit = await mobile.locator('[data-res-quote] .word').evaluateAll((els) => els.filter((el) => Number(getComputedStyle(el).opacity) > 0.5).length);
    check(lit > 0, `mobile : les mots s'allument aussi en défilant (${lit})`);
    await mobile.context().close();

    // ── Mouvement réduit : tout est visible tout de suite, rien n'est épinglé ──
    const calm = await open({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
    await new Promise((r) => setTimeout(r, 1200));
    check(await calm.locator('[data-res-title]').evaluate((el) => Number(getComputedStyle(el).opacity) === 1 && el.querySelectorAll('.char').length === 0), 'mouvement réduit : le titre est visible, non découpé');
    check(await calm.locator('[data-res-quote]').evaluate((el) => el.querySelectorAll('.word').length === 0 && Number(getComputedStyle(el).opacity) === 1), 'mouvement réduit : la phrase est entièrement lisible');
    check((await calm.locator('.pin-spacer').count()) === 0, 'mouvement réduit : aucune scène épinglée');
    check(await calm.locator('[data-res-person]').first().evaluate((el) => Number(getComputedStyle(el).opacity) === 1), 'mouvement réduit : les fiches sont visibles');
    await calm.context().close();

    // ── Anglais ──
    const en = await open({ viewport: { width: 1440, height: 900 } }, '/en/centre-de-recherche');
    check(await en.getAttribute('html', 'lang') === 'en', 'anglais : <html lang="en">');
    check((await en.locator('h1').innerText()).trim().toLowerCase().replace(/\s+/g, ' ') === 'research centre', 'anglais : titre « Research centre »');
    check((await en.locator('[data-res-quote]').innerText()).startsWith('Thanks to interactive learning'), 'anglais : la phrase est traduite');
    await en.context().close();

    check(errors.length === 0, `aucune erreur JavaScript${errors.length ? ' : ' + errors.join('; ') : ''}`);
} catch (error) {
    console.log(`✗ parcours interrompu : ${error.message.split('\n')[0]}`);
    failures++;
} finally {
    await browser.close();
}

process.exit(failures ? 1 : 0);
