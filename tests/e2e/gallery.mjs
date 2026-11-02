// Galerie photo dans un vrai navigateur : titre intact, nombres comptés, mur du héros qui dérive, cadres dévoilés, projecteur et curseur,
// filtre par année (Flip), page d'un album et visionneuse, mobile, mouvement réduit, anglais.
// Crée six albums d'essai « E2E … » (tests/e2e/fixtures/gallery-albums.php) puis les supprime.
// Prérequis : serveur lancé, base de développement avec des images dans la médiathèque. Usage : node tests/e2e/gallery.mjs [dossier-captures]
// Variable PHP_BIN : exécutable PHP (par défaut « php »).
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const out = process.argv[2];
if (out) fs.mkdirSync(out, { recursive: true });
const fixture = path.join(path.dirname(fileURLToPath(import.meta.url)), 'fixtures', 'gallery-albums.php');
const albums = (action) => execFileSync(process.env.PHP_BIN ?? 'php', [fixture, action], { encoding: 'utf8' });

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
const open = async (options, url = '/galerie') => {
    const context = await browser.newContext({ locale: 'fr-FR', ...options });
    await context.addInitScript(() => localStorage.setItem('uaz.newsletter', JSON.stringify({ subscribed: true })));
    const page = await context.newPage();
    page.on('pageerror', (e) => !/ViewTransition|Transition was aborted/.test(e.message) && errors.push(e.message.slice(0, 140)));
    await page.goto(base + url, { waitUntil: 'load', timeout: 90000 });

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
const shown = (page) => page.locator('[data-gal-frame]:not([hidden])').count();

try {
    albums('up');

    // ── Ordinateur ──
    const page = await open({ viewport: { width: 1440, height: 900 } });
    check(await until(async () => page.evaluate(() => document.documentElement.classList.contains('gal-ready'))), 'le mode immersif s\'active (gal-ready)');
    check(await until(async () => (await page.locator('[data-gal-title] .char').count()) >= 10), 'le titre se découpe en lettres');
    check(await page.locator('[data-gal-title] .word').evaluateAll((els) => els.every((el) => el.getBoundingClientRect().height < 1.4 * parseFloat(getComputedStyle(el).fontSize))), 'aucun mot du titre n\'est coupé en deux lignes');
    check(await until(async () => (await page.locator('[data-gal-count]').first().innerText()).trim() === '6', 8000), 'le nombre d\'albums est compté puis rétabli (6)');
    check(await page.locator('[data-gal-col]').count() === 3, 'le mur du héros compte trois colonnes');
    const before = await page.locator('[data-gal-col]').first().evaluate((el) => getComputedStyle(el).transform);
    await wait(1500);
    check(before !== await page.locator('[data-gal-col]').first().evaluate((el) => getComputedStyle(el).transform), 'le mur du héros dérive');
    if (out) await page.screenshot({ path: path.join(out, 'galerie-hero.png') });

    // Les cadres se dévoilent ; la photo glisse dans son passe-partout.
    await scrollTo(page, '[data-gal-wall]', -150);
    check(await until(async () => page.locator('[data-gal-mat]').first().evaluate((el) => getComputedStyle(el).clipPath === 'none'), 8000), 'le premier cadre est dévoilé (rideau levé)');
    const t1 = await page.locator('[data-gal-img]').first().evaluate((el) => getComputedStyle(el).transform);
    await page.evaluate(() => window.scrollBy(0, 250));
    await wait(700);
    check(t1 !== await page.locator('[data-gal-img]').first().evaluate((el) => getComputedStyle(el).transform), 'la photo glisse dans son cadre en défilant');

    // Projecteur et curseur.
    const box = await page.locator('[data-gal-frame]').first().locator('[data-gal-mat]').boundingBox();
    await page.mouse.move(box.x + box.width / 2, Math.max(120, box.y + box.height / 2), { steps: 8 });
    await wait(900);
    check(await page.locator('[data-gal-expo]').evaluate((el) => el.classList.contains('is-spot')), 'le projecteur suit le pointeur (is-spot)');
    check(await until(async () => Number(await page.locator('[data-gal-cursor]').evaluate((el) => getComputedStyle(el).opacity)) > 0.9, 3000), 'le curseur « Ouvrir » apparaît au-dessus d\'un cadre');
    if (out) await page.screenshot({ path: path.join(out, 'galerie-mur.png') });

    // Filtre par année : Flip réorganise le mur.
    await scrollTo(page, '[data-gal-filter]', -200);
    check(await page.locator('[data-gal-filter]').isVisible(), 'le filtre par année est affiché');
    const years = await page.locator('[data-gal-year]').evaluateAll((els) => els.map((el) => el.dataset.galYear));
    check(years.join() === 'all,2026,2025,2024', `les années viennent des albums (${years.join(', ')})`);
    await page.locator('[data-gal-year="2025"]').click();
    check(await until(async () => (await shown(page)) === 2, 6000), `filtre 2025 : deux albums restent (${await shown(page)})`);
    check(await page.locator('[data-gal-year="2025"]').getAttribute('aria-pressed') === 'true', 'filtre : le bouton actif est signalé (aria-pressed)');
    check((await page.locator('[data-gal-status]').innerText()).startsWith('2 albums'), 'filtre : le résultat est annoncé aux lecteurs d\'écran');
    check(await page.locator('[data-gal-frame]:not([hidden])').evaluateAll((els) => els.map((el) => el.dataset.slot).join() === '0,1'), 'filtre : le rythme d\'accrochage suit les cadres visibles');
    await wait(1200);
    if (out) await page.screenshot({ path: path.join(out, 'galerie-filtre.png') });
    await page.locator('[data-gal-year="all"]').click();
    check(await until(async () => (await shown(page)) === 6, 6000), 'filtre « Toutes » : les six albums reviennent');

    // Un album s'ouvre.
    const link = page.locator('[data-gal-link]').first();
    check(/\/galerie\/e2e-a$/.test(await link.getAttribute('href')), 'le titre mène à la page de l\'album');
    await page.context().close();

    // ── Page d'un album ──
    const album = await open({ viewport: { width: 1440, height: 900 } }, '/galerie/e2e-a');
    check(await until(async () => album.evaluate(() => document.documentElement.classList.contains('gal-ready'))), 'album : le mode immersif s\'active');
    check(await album.locator('[data-gal-cover]').count() === 1, 'album : la couverture du héros est présente');
    check(await album.locator('[data-gal-frame]').count() === 6, 'album : six photos sont accrochées');
    await scrollTo(album, '[data-gal-wall]', -150);
    await wait(1800);
    if (out) await album.screenshot({ path: path.join(out, 'galerie-album.png') });
    await album.locator('[data-lightbox-item]').first().click();
    check(await until(async () => album.evaluate(() => [...document.querySelectorAll('dialog')].some((d) => d.open && d.getAttribute('aria-label') === 'E2E A')), 4000), 'album : la visionneuse s\'ouvre sur la photo');
    check((await album.locator('dialog[open] [aria-live]').innerText()).trim() === '1 / 6', 'album : la visionneuse compte « 1 / 6 »');
    await album.keyboard.press('ArrowRight');
    check(await until(async () => (await album.locator('dialog[open] [aria-live]').innerText()).trim() === '2 / 6', 3000), 'album : la flèche droite passe à la photo suivante');
    await album.keyboard.press('Escape');
    await album.context().close();

    // ── Mobile ──
    const mobile = await open({ viewport: { width: 390, height: 844 } });
    await until(async () => mobile.evaluate(() => document.documentElement.classList.contains('gal-ready')));
    check((await mobile.evaluate(() => document.documentElement.scrollWidth)) <= 390, 'mobile : aucun défilement horizontal');
    check(!(await mobile.locator('[data-gal-expo]').evaluate((el) => el.classList.contains('is-spot'))), 'mobile : pas de projecteur');
    await scrollTo(mobile, '[data-gal-wall]', -100);
    await wait(1500);
    check(await mobile.locator('[data-gal-mat]').first().evaluate((el) => { const r = el.getBoundingClientRect(); return r.width > 300 && r.right <= 390; }), 'mobile : les cadres occupent la largeur de l\'écran');
    if (out) await mobile.screenshot({ path: path.join(out, 'galerie-mobile.png') });
    await mobile.context().close();

    // ── Mouvement réduit ──
    const calm = await open({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
    await wait(1200);
    check(await calm.locator('[data-gal-title]').evaluate((el) => Number(getComputedStyle(el).opacity) === 1 && el.querySelectorAll('.char').length === 0), 'mouvement réduit : le titre est visible, non découpé');
    check(await calm.locator('[data-gal-mat]').evaluateAll((els) => els.length === 6 && els.every((el) => getComputedStyle(el).clipPath === 'none')), 'mouvement réduit : tous les cadres sont visibles');
    check(!(await calm.locator('[data-gal-expo]').evaluate((el) => el.classList.contains('is-spot'))), 'mouvement réduit : pas de projecteur');
    await scrollTo(calm, '[data-gal-filter]', -200);
    check(await until(async () => calm.locator('[data-gal-filter]').isVisible(), 4000), 'mouvement réduit : le filtre est disponible');
    await calm.locator('[data-gal-year="2026"]').click();
    check(await until(async () => (await shown(calm)) === 3, 3000), 'mouvement réduit : le filtre fonctionne sans animation (trois albums de 2026)');
    await calm.context().close();

    // ── Anglais ──
    const en = await open({ viewport: { width: 1440, height: 900 } }, '/en/galerie');
    check(await en.getAttribute('html', 'lang') === 'en', 'anglais : <html lang="en">');
    check((await en.locator('[data-gal-year="all"]').innerText()).trim() === 'All', 'anglais : « All »');
    await en.context().close();

    check(errors.length === 0, `aucune erreur JavaScript${errors.length ? ' : ' + errors.join('; ') : ''}`);
} catch (error) {
    console.log(`✗ parcours interrompu : ${error.message.split('\n')[0]}`);
    failures++;
} finally {
    await browser.close();
    try { albums('down'); } catch (error) { console.log(`✗ nettoyage des albums d'essai impossible : ${error.message.split('\n')[0]}`); failures++; }
}

process.exit(failures ? 1 : 0);
