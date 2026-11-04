// Page « Association des étudiants » dans un vrai navigateur : titre intact, lettres « SA » remplies d'une photo qui suivent le pointeur, phrases qui
// s'allument, chaîne VRAE → SA → activités, foule d'étudiants et élus, candidature épinglée (quinze sièges, compteur 0 → 15, appel, entretien avec le VRAE),
// planification où les projets du VRAE passent en tête, menu, mobile, mouvement réduit, anglais.
// Prérequis : serveur lancé, base de développement (migration de la page appliquée). Usage : node tests/e2e/association.mjs [dossier-captures]
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
const url = '/vie-etudiante/association-des-etudiants';

const browser = await chromium.launch();
const errors = [];
const open = async (options, target = url) => {
    const context = await browser.newContext({ locale: 'fr-FR', ...options });
    await context.addInitScript(() => localStorage.setItem('uaz.newsletter', JSON.stringify({ subscribed: true })));
    const page = await context.newPage();
    page.on('pageerror', (e) => !/ViewTransition|Transition was aborted/.test(e.message) && errors.push(e.message.slice(0, 140)));
    await page.goto(base + target, { waitUntil: 'load', timeout: 90000 });

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
const num = (page) => page.locator('[data-sa-num]').evaluate((el) => Number(el.textContent));
const shown = (page) => page.locator('[data-sa-step]').evaluateAll((els) => els.map((el) => Number(getComputedStyle(el).opacity) > 0.9));
const seatsOn = (page) => page.locator('[data-sa-seat]').evaluateAll((els) => els.filter((el) => Number(getComputedStyle(el).opacity) > 0.9 && getComputedStyle(el).transform !== 'matrix(0, 0, 0, 0, 0, 0)').length);
const alpha = (page, selector) => page.locator(selector).evaluate((el) => Number(getComputedStyle(el).opacity));

try {
    // ── Ordinateur ──
    const page = await open({ viewport: { width: 1440, height: 900 } });
    check(await until(async () => page.evaluate(() => document.documentElement.classList.contains('sa-ready'))), 'le mode immersif s\'active (sa-ready)');
    check(await until(async () => (await page.locator('[data-sa-title] .char').count()) >= 20), 'le titre se découpe en lettres');
    check(await page.locator('[data-sa-title] .word').evaluateAll((els) => els.every((el) => el.getBoundingClientRect().height < 1.4 * parseFloat(getComputedStyle(el).fontSize))), 'aucun mot du titre n\'est coupé en deux lignes');
    await wait(2200);
    check(await page.locator('[data-sa-letter]').count() === 2, 'deux lettres : S et A');
    check(await page.locator('[data-sa-letter]').first().evaluate((el) => getComputedStyle(el).backgroundImage.includes('url(') || getComputedStyle(el).backgroundImage.includes('gradient')), 'les lettres sont remplies d\'une image (photo ou dégradé or)');
    if (out) await page.screenshot({ path: path.join(out, 'association-hero.png') });

    // Les lettres suivent le pointeur.
    const letter = page.locator('[data-sa-letter]').nth(1);
    const x0 = (await letter.boundingBox()).x;
    await page.mouse.move(1350, 520, { steps: 8 });
    await wait(1300);
    check(Math.abs((await letter.boundingBox()).x - x0) > 3, 'les lettres suivent le pointeur');
    await page.mouse.move(5, 5);

    // Son rôle.
    await scrollTo(page, (await top(page, '#role')) - (await header(page)) - 40);
    await wait(2800);
    check(await page.locator('[data-sa-node]').evaluateAll((els) => els.length === 3 && els.every((el) => Number(getComputedStyle(el).opacity) > 0.95)), 'rôle : VRAE, SA et activités sont posés');
    check(await page.locator('.sa-link__line').evaluateAll((els) => els.every((el) => getComputedStyle(el).transform !== 'matrix(1, 0, 0, 0, 0, 0)')), 'rôle : les traits entre les nœuds sont tracés');
    check(await page.locator('[data-sa-statement]').first().evaluate((el) => el.querySelectorAll('.word').length >= 8), 'rôle : la phrase se découpe en mots');
    if (out) await page.screenshot({ path: path.join(out, 'association-role.png') });

    // Ses officiants : la foule s'allume, les élus grandissent.
    await scrollTo(page, (await top(page, '[data-sa-crowd]')) - 260);
    await wait(4200);
    check(await page.locator('[data-sa-crowd] i').evaluateAll((els) => els.length === 96 && els.every((el) => Number(getComputedStyle(el).opacity) > 0.95)), 'officiants : les 96 points sont allumés');
    check(await page.locator('[data-sa-crowd] i.is-elected').first().evaluate((el) => new DOMMatrix(getComputedStyle(el).transform).a > 1.4), 'officiants : les élus ont grandi');

    // Se présenter : épinglé, quinze sièges, appel, entretien.
    const stage = await top(page, '[data-sa-stage]');
    await scrollTo(page, stage - (await header(page)));
    await wait(1200);
    check(await page.locator('[data-sa-stage]').evaluate((el) => el.parentElement?.classList.contains('pin-spacer')), 'candidature : la scène est épinglée sur grand écran');
    check((await num(page)) === 0 && (await shown(page)).join() === 'true,false,false', 'au départ : compteur à 0, seule l\'étape 1 est lisible');
    check((await alpha(page, '[data-sa-vrae]')) < 0.05, 'au départ : le VRAE n\'est pas encore là');
    const start = await page.evaluate(() => scrollY);
    await scrollTo(page, start + 400);
    check(await until(async () => (await num(page)) > 5, 4000), `étape 1 : le compteur monte vers 15 (${await num(page)})`);
    await scrollTo(page, start + 750);
    check(await until(async () => (await num(page)) === 15 && (await seatsOn(page)) === 15, 4000), 'étape 1 : 15 sièges posés, compteur à 15');
    check((await alpha(page, '[data-sa-cap="1"]')) > 0.9, 'étape 1 : « noms au plus »');
    await scrollTo(page, start + 1350);
    check(await until(async () => (await shown(page)).join() === 'false,true,false', 4000), 'étape 2 : l\'appel à candidature prend la place');
    check((await alpha(page, '[data-sa-cap="2"]')) > 0.9, 'étape 2 : « Appel officiel »');
    if (out) await page.screenshot({ path: path.join(out, 'association-appel.png') });
    await scrollTo(page, start + 2000);
    check(await until(async () => (await shown(page)).join() === 'false,false,true', 5000), 'étape 3 : l\'entretien avec le VRAE prend la place');
    check(await until(async () => (await alpha(page, '[data-sa-vrae]')) > 0.95, 4000), 'étape 3 : le VRAE apparaît');
    check(await until(async () => page.locator('[data-sa-vlink]').evaluate((el) => getComputedStyle(el).strokeDasharray !== 'none'), 3000), 'étape 3 : le lien VRAE → équipe se dessine');
    if (out) await page.screenshot({ path: path.join(out, 'association-entretien.png') });

    // Engagement : les projets du VRAE passent en tête.
    await scrollTo(page, (await top(page, '[data-sa-plan]')) - 220);
    await wait(5200);
    const lanes = await page.locator('[data-sa-lane]').evaluateAll((els) => els.map((el) => ({ lane: el.dataset.saLane, top: el.getBoundingClientRect().top })));
    check(lanes.find((l) => l.lane === 'vrae').top < lanes.find((l) => l.lane === 'others').top, 'engagement : les projets du VRAE sont au-dessus des autres activités');
    check(await page.locator('[data-sa-bar]').evaluateAll((els) => els.every((el) => new DOMMatrix(getComputedStyle(el).transform).a > 0.95)), 'engagement : toutes les barres sont tracées');
    await scrollTo(page, (await top(page, '.sa-principles')) - 400);
    await wait(2200);
    check(await page.locator('[data-sa-principle]').evaluateAll((els) => els.length === 3 && els.every((el) => Number(getComputedStyle(el).opacity) > 0.95)), 'engagement : les trois principes sont posés');
    if (out) await page.screenshot({ path: path.join(out, 'association-engagement.png') });
    await page.context().close();

    // ── Menu ──
    const menu = await open({ viewport: { width: 1440, height: 900 } }, '/');
    await menu.locator('header button', { hasText: 'Vie étudiante' }).first().hover();
    check(await until(async () => menu.locator('header a', { hasText: 'Association des étudiants' }).first().isVisible(), 3000), 'le menu Vie étudiante propose « Association des étudiants »');
    await menu.locator('header a', { hasText: 'Association des étudiants' }).first().click();
    check(await until(async () => menu.url().endsWith(url), 8000), 'le lien mène à la page');
    await menu.context().close();

    // ── Mobile ──
    const mobile = await open({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    await wait(2500);
    const height = await mobile.evaluate(() => document.documentElement.scrollHeight);
    for (let y = 0; y < height; y += 400) { await mobile.evaluate((v) => scrollTo(0, v), y); await wait(30); }
    await wait(3000);
    check((await mobile.evaluate(() => document.documentElement.scrollWidth)) <= 390, 'mobile : aucun défilement horizontal');
    check((await mobile.locator('.pin-spacer').count()) === 0, 'mobile : rien n\'est épinglé');
    check(await num(mobile) === 15 && (await seatsOn(mobile)) === 15, 'mobile : quinze sièges posés, compteur à 15');
    check((await alpha(mobile, '[data-sa-vrae]')) > 0.95, 'mobile : le VRAE rejoint l\'équipe');
    check((await shown(mobile)).every(Boolean), 'mobile : les trois étapes sont lisibles');
    if (out) { await mobile.evaluate(() => document.querySelector('[data-sa-hemi]').scrollIntoView()); await wait(1200); await mobile.screenshot({ path: path.join(out, 'association-mobile.png') }); }
    await mobile.context().close();

    // ── Mouvement réduit ──
    const calm = await open({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
    await wait(1200);
    check(await calm.locator('[data-sa-title] .char').count() === 0, 'mouvement réduit : le titre n\'est pas découpé');
    check((await calm.locator('.pin-spacer').count()) === 0, 'mouvement réduit : rien n\'est épinglé');
    check(await num(calm) === 15 && (await seatsOn(calm)) === 15 && (await alpha(calm, '[data-sa-vrae]')) === 1, 'mouvement réduit : quinze sièges, compteur à 15, VRAE présent');
    check((await shown(calm)).every(Boolean), 'mouvement réduit : les trois étapes sont lisibles');
    check(await calm.locator('[data-sa-bar]').evaluateAll((els) => els.every((el) => getComputedStyle(el).transform === 'none')), 'mouvement réduit : la planification est déjà ordonnée');
    await calm.context().close();

    // ── Anglais ──
    const en = await open({ viewport: { width: 1440, height: 900 } }, `/en${url}`);
    check(await en.getAttribute('html', 'lang') === 'en', 'anglais : <html lang="en">');
    check((await en.locator('[data-sa-title]').innerText()).toLowerCase().includes('student association'), 'anglais : le titre');
    check((await en.locator('[data-sa-cap="1"]').textContent()).trim() === 'names at most', 'anglais : « names at most »');
    await en.context().close();

    check(errors.length === 0, `aucune erreur JavaScript${errors.length ? ' : ' + errors.join('; ') : ''}`);
} catch (error) {
    console.log(`✗ parcours interrompu : ${error.message.split('\n')[0]}`);
    failures++;
} finally {
    await browser.close();
}

process.exit(failures ? 1 : 0);
