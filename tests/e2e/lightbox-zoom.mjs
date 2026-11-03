// Zoom de la visionneuse de la galerie : boutons + / − / pourcentage, molette, double-clic, touches, glissement d'une photo
// zoomée, retour à 100 % au changement de photo, bornes, mobile (pincement simulé), anglais. Albums d'essai : voir
// tests/e2e/fixtures/gallery-albums.php. Prérequis : serveur lancé. Usage : node tests/e2e/lightbox-zoom.mjs [dossier-captures]
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
const wait = (ms) => new Promise((r) => setTimeout(r, ms));

albums('up');
const browser = await chromium.launch();
const errors = [];
try {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'fr-FR' });
    await context.addInitScript(() => localStorage.setItem('uaz.newsletter', JSON.stringify({ subscribed: true })));
    const page = await context.newPage();
    page.on('pageerror', (e) => errors.push(e.message.slice(0, 140)));
    await page.goto(`${base}/galerie/e2e-a`, { waitUntil: 'load', timeout: 90000 });
    await wait(1500);

    const dialog = page.locator('dialog[aria-label="E2E A"]');
    const photo = dialog.locator('img');
    const label = dialog.locator('button[aria-label="Taille d\'origine"] span');
    const scale = () => photo.evaluate((el) => {
        const m = getComputedStyle(el).transform;
        return m === 'none' ? 1 : Number(m.match(/matrix\(([^,]+)/)[1]);
    });
    const shift = () => photo.evaluate((el) => {
        const m = getComputedStyle(el).transform;
        return m === 'none' ? [0, 0] : m.match(/matrix\(([^)]+)\)/)[1].split(',').slice(4).map(Number);
    });

    await page.locator('[data-lightbox-item]').first().scrollIntoViewIfNeeded();
    await page.locator('[data-lightbox-item]').first().evaluate((el) => el.click());
    await dialog.waitFor({ state: 'visible' });
    check(await label.innerText() === '100 %' && (await scale()) === 1, 'ouverture : 100 %');

    // Boutons.
    await dialog.getByRole('button', { name: 'Zoom avant' }).click();
    check(Math.abs((await scale()) - 1.5) < 0.01 && (await label.innerText()) === '150 %', 'bouton + : 150 %');
    await dialog.getByRole('button', { name: 'Zoom avant' }).click();
    check(Math.abs((await scale()) - 2.25) < 0.01, 'bouton + encore : 225 %');
    await dialog.getByRole('button', { name: 'Zoom arrière' }).click();
    check(Math.abs((await scale()) - 1.5) < 0.01, 'bouton − : retour à 150 %');
    if (out) await page.screenshot({ path: path.join(out, 'zoom-bouton.png') });
    await dialog.getByRole('button', { name: 'Taille d\'origine' }).click();
    check((await scale()) === 1 && (await label.innerText()) === '100 %', 'le pourcentage rétablit 100 %');

    // Bornes.
    for (let i = 0; i < 12; i++) await dialog.getByRole('button', { name: 'Zoom avant' }).click();
    check(Math.abs((await scale()) - 5) < 0.01, 'le zoom est limité à 500 %');
    await page.keyboard.press('0');
    for (let i = 0; i < 6; i++) await dialog.getByRole('button', { name: 'Zoom arrière' }).click();
    check((await scale()) === 1, 'le zoom ne descend pas sous 100 %');

    // Clavier.
    await page.keyboard.press('+');
    await page.keyboard.press('+');
    check(Math.abs((await scale()) - 2.25) < 0.01, 'touche + (deux fois) : 225 %');
    await page.keyboard.press('-');
    check(Math.abs((await scale()) - 1.5) < 0.01, 'touche − : 150 %');
    await page.keyboard.press('0');
    check((await scale()) === 1, 'touche 0 : 100 %');

    // Molette centrée sur le pointeur : le point visé reste en place.
    const box = await photo.boundingBox();
    const px = box.x + box.width * 0.8;
    const py = box.y + box.height * 0.3;
    await page.mouse.move(px, py);
    await page.mouse.wheel(0, -400);
    await wait(150);
    check((await scale()) > 1.3, `molette vers le haut : agrandit (${(await scale()).toFixed(2)})`);
    const after = await photo.boundingBox();
    const relBefore = [(px - box.x) / box.width, (py - box.y) / box.height];
    const relAfter = [(px - after.x) / after.width, (py - after.y) / after.height];
    check(Math.abs(relBefore[0] - relAfter[0]) < 0.03 && Math.abs(relBefore[1] - relAfter[1]) < 0.03, 'molette : le point sous le pointeur reste en place');
    await page.mouse.wheel(0, 2000);
    await wait(150);
    check((await scale()) === 1, 'molette vers le bas : revient à 100 %');

    // Double-clic, puis glissement.
    await photo.dblclick();
    check(Math.abs((await scale()) - 2.5) < 0.05, 'double-clic : 250 %');
    const [x0, y0] = await shift();
    const zoomed = await photo.boundingBox();
    await page.mouse.move(zoomed.x + zoomed.width / 2, zoomed.y + zoomed.height / 2);
    await page.mouse.down();
    await page.mouse.move(zoomed.x + zoomed.width / 2 - 120, zoomed.y + zoomed.height / 2 - 60, { steps: 6 });
    await page.mouse.up();
    const [x1, y1] = await shift();
    check(x1 < x0 - 50 && y1 < y0 - 20, `glisser déplace la photo zoomée (${Math.round(x1 - x0)}, ${Math.round(y1 - y0)})`);
    const frame = await photo.boundingBox();
    check(frame.x <= 1440 && frame.x + frame.width >= 0, 'la photo ne sort pas entièrement du cadre');
    for (let i = 0; i < 6; i++) await page.mouse.wheel(0, 0);
    await page.mouse.move(700, 450);
    await page.mouse.down();
    await page.mouse.move(-2000, -2000, { steps: 4 });
    await page.mouse.up();
    const [xe, ye] = await shift();
    const limitX = (await photo.evaluate((el) => el.offsetWidth)) * ((await scale()) - 1) / 2;
    check(Math.abs(xe) <= limitX + 1 && Math.abs(ye) >= 0, 'le déplacement est borné au cadre d\'origine');
    if (out) await page.screenshot({ path: path.join(out, 'zoom-deplace.png') });
    await photo.dblclick();
    check((await scale()) === 1, 'double-clic sur une photo zoomée : retour à 100 %');

    // Changement de photo : le zoom est rétabli.
    await page.keyboard.press('+');
    await dialog.getByRole('button', { name: 'Photo suivante' }).click();
    check((await scale()) === 1 && (await dialog.locator('[aria-live]').innerText()).trim().startsWith('2 /'), 'photo suivante : 100 % et compteur 2');

    // Fermer, rouvrir : toujours à 100 %.
    await page.keyboard.press('Escape');
    await dialog.waitFor({ state: 'hidden' });
    await page.locator('[data-lightbox-item]').nth(2).evaluate((el) => el.click());
    await dialog.waitFor({ state: 'visible' });
    check((await scale()) === 1, 'à la réouverture : 100 %');
    await context.close();

    // Mobile : pincement simulé par deux pointeurs.
    const mobile = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, locale: 'fr-FR' });
    await mobile.addInitScript(() => localStorage.setItem('uaz.newsletter', JSON.stringify({ subscribed: true })));
    const m = await mobile.newPage();
    m.on('pageerror', (e) => errors.push(e.message.slice(0, 140)));
    await m.goto(`${base}/galerie/e2e-a`, { waitUntil: 'load', timeout: 90000 });
    await wait(1500);
    await m.locator('[data-lightbox-item]').first().scrollIntoViewIfNeeded();
    await m.locator('[data-lightbox-item]').first().evaluate((el) => el.click());
    const md = m.locator('dialog[aria-label="E2E A"]');
    await md.waitFor({ state: 'visible' });
    check(await md.getByRole('button', { name: 'Zoom avant' }).isVisible(), 'mobile : les boutons de zoom sont visibles');
    await md.getByRole('button', { name: 'Zoom avant' }).click();
    const ms = await md.locator('img').evaluate((el) => getComputedStyle(el).transform);
    check(ms !== 'none', 'mobile : le bouton + agrandit');
    const pinched = await md.locator('img').evaluate((el) => {
        const r = el.getBoundingClientRect();
        const cx = r.left + r.width / 2;
        const cy = r.top + r.height / 2;
        const fire = (type, id, x) => el.dispatchEvent(new PointerEvent(type, { pointerId: id, clientX: x, clientY: cy, bubbles: true, pointerType: 'touch' }));
        fire('pointerdown', 1, cx - 30);
        fire('pointerdown', 2, cx + 30);
        fire('pointermove', 1, cx - 90);
        fire('pointermove', 2, cx + 90);
        fire('pointerup', 1, cx - 90);
        fire('pointerup', 2, cx + 90);
        const m2 = getComputedStyle(el).transform;
        return m2 === 'none' ? 1 : Number(m2.match(/matrix\(([^,]+)/)[1]);
    });
    check(pinched > 2.2, `mobile : le pincement agrandit (${pinched.toFixed(2)})`);
    if (out) await m.screenshot({ path: path.join(out, 'zoom-mobile.png') });
    await mobile.close();

    // Anglais : libellés traduits.
    const en = await (await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'en-GB' })).newPage();
    await en.addInitScript(() => localStorage.setItem('uaz.newsletter', JSON.stringify({ subscribed: true })));
    await en.goto(`${base}/en/galerie/e2e-a`, { waitUntil: 'load', timeout: 90000 });
    await wait(1200);
    await en.locator('[data-lightbox-item]').first().evaluate((el) => el.click());
    const ed = en.locator('dialog[aria-label="E2E A"]');
    await ed.waitFor({ state: 'visible' });
    check(await ed.getByRole('button', { name: 'Zoom in' }).count() === 1 && await ed.getByRole('button', { name: 'Zoom out' }).count() === 1 && await ed.getByRole('button', { name: 'Original size' }).count() === 1, 'anglais : « Zoom in », « Zoom out », « Original size »');

    check(errors.length === 0, `aucune erreur JavaScript${errors.length ? ' : ' + errors.join('; ') : ''}`);
} catch (error) {
    console.log(`✗ parcours interrompu : ${error.message.split('\n')[0]}`);
    failures++;
} finally {
    await browser.close();
    albums('down');
}

process.exit(failures ? 1 : 0);
