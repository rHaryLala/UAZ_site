// Accueil dans un vrai navigateur : le héros et la vidéo « Votre futur commence ici » sont intacts ; phrase qui s'allume, chiffres comptés, domaines qui s'ouvrent
// (souris, clavier), sceau, pile des lieux, titre géant, sommaire à image suiveuse, billets d'événements, fermeture, mobile, mouvement réduit, anglais.
// Crée des événements d'essai « E2E … » (tests/e2e/fixtures/events.php) puis les supprime.
// Prérequis : serveur lancé, base de développement. Usage : node tests/e2e/home.mjs [dossier-captures]. Variable PHP_BIN : exécutable PHP.
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const out = process.argv[2];
if (out) fs.mkdirSync(out, { recursive: true });
const fixture = path.join(path.dirname(fileURLToPath(import.meta.url)), 'fixtures', 'events.php');
const events = (action) => execFileSync(process.env.PHP_BIN ?? 'php', [fixture, action], { encoding: 'utf8' });

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
const open = async (options, url = '/') => {
    const context = await browser.newContext({ locale: 'fr-FR', ...options });
    await context.addInitScript(() => localStorage.setItem('uaz.newsletter', JSON.stringify({ subscribed: true })));
    const page = await context.newPage();
    page.on('pageerror', (e) => !/ViewTransition|Transition was aborted/.test(e.message) && errors.push(e.message.slice(0, 140)));
    await page.goto(base + url, { waitUntil: 'load', timeout: 90000 });

    return page;
};
const top = (page, selector) => page.locator(selector).first().evaluate((el) => el.getBoundingClientRect().top + window.scrollY);
const scrollTo = async (page, y) => {
    for (let at = await page.evaluate(() => scrollY); at < y; at += 450) {
        await page.evaluate((v) => window.scrollTo(0, v), at);
        await wait(35);
    }
    await page.evaluate((v) => window.scrollTo(0, v), y);
};
const gotoSection = async (page, selector, offset = 0) => scrollTo(page, (await top(page, selector)) + offset);
const header = (page) => page.evaluate(() => document.querySelector('.site-header').offsetHeight);

try {
    events('up');

    // ── Ordinateur ──
    const page = await open({ viewport: { width: 1440, height: 900 } });
    await wait(3200);
    check(await page.locator('[data-hero-title]').count() === 1 && await page.locator('[data-hero-ticker]').count() === 1, 'le héros est intact (titre et phrase en trois langues)');
    check(await page.locator('[data-future-video]').count() === 1 && await page.locator('[data-future-title] [lang]').count() === 3, 'la section « Votre futur commence ici » (glitch + vidéo) est intacte');

    // 1. La différence : phrase épinglée qui s'allume.
    await gotoSection(page, '[data-home="diff"]', -(await header(page)));
    await wait(1200);
    const words = await page.locator('[data-home-light] .word').count();
    check(words >= 6, `la phrase est découpée en mots (${words})`);
    check(await page.locator('[data-home-stage]').evaluate((el) => el.parentElement?.classList.contains('pin-spacer')), 'la phrase est épinglée sur grand écran');
    const dim = await page.locator('[data-home-light] .word').evaluateAll((els) => els.filter((el) => Number(getComputedStyle(el).opacity) < 0.6).length);
    check(dim > words * 0.6, `à l'arrivée la plupart des mots sont éteints (${dim}/${words})`);
    const stage = await top(page, '[data-home-stage]');
    await scrollTo(page, stage + 2200);
    check(await until(async () => page.locator('[data-home-light] .word').evaluateAll((els) => els.every((el) => Number(getComputedStyle(el).opacity) > 0.98)), 5000), 'en défilant, tous les mots sont allumés');
    check(await until(async () => page.locator('[data-home-after]').evaluate((el) => Number(getComputedStyle(el).opacity) > 0.95), 5000), 'le texte et le lien apparaissent ensuite');
    await gotoSection(page, '[data-home-figs]', -250);
    check(await until(async () => page.locator('[data-counter]').evaluateAll((els) => els.length === 4 && els.every((el) => el.textContent.replace(/\s/g, '') === String(Number(el.dataset.value)))), 8000), 'les quatre chiffres clés finissent à leur valeur');
    check(await page.locator('.home-fig__rule').first().evaluate((el) => new DOMMatrix(getComputedStyle(el).transform).a > 0.98), 'le filet d\'or des chiffres est tracé');

    // 2. Les domaines : un panneau ouvert, les autres s'ouvrent au survol et au clavier.
    await gotoSection(page, '[data-home="dom"]', 120);
    await wait(1500);
    check(await page.locator('[data-home-panel]').count() === 4, 'quatre domaines de formation');
    check(await page.locator('[data-home-panel]').first().evaluate((el) => el.classList.contains('is-open')), 'le premier panneau est ouvert au départ');
    await page.locator('[data-home-panel]').nth(2).hover();
    check(await until(async () => page.locator('[data-home-panel]').nth(2).evaluate((el) => el.classList.contains('is-open')), 2000), 'survol : le troisième panneau s\'ouvre');
    check(await until(async () => page.locator('[data-home-panel]').evaluateAll((els) => els[2].getBoundingClientRect().width > els[0].getBoundingClientRect().width * 1.8), 3000), 'il s\'élargit pendant que les autres se resserrent');
    if (out) await page.screenshot({ path: path.join(out, 'accueil-domaines.png') });
    await page.locator('[data-home-panel]').nth(3).locator('a').first().focus();
    check(await until(async () => page.locator('[data-home-panel]').nth(3).evaluate((el) => el.classList.contains('is-open')), 2000), 'clavier : le focus ouvre le panneau du lien');
    await page.mouse.move(5, 5);

    // 3. Admissions : sceau, anneaux, inclinaison.
    await gotoSection(page, '[data-home="adm"]', 60);
    await wait(2800);
    check(await page.locator('[data-home-ring]').evaluate((el) => { const o = parseFloat(getComputedStyle(el).strokeDashoffset); return Number.isNaN(o) || o < 3 || getComputedStyle(el).strokeDasharray === 'none'; }), 'admissions : les anneaux du sceau sont tracés');
    const seal0 = await page.locator('[data-home-seal-img]').evaluate((el) => getComputedStyle(el).transform);
    const sealBox = await page.locator('[data-home-seal]').boundingBox();
    await page.mouse.move(sealBox.x + sealBox.width * 0.95, sealBox.y + 12, { steps: 8 });
    await wait(1100);
    check(seal0 !== await page.locator('[data-home-seal-img]').evaluate((el) => getComputedStyle(el).transform), 'admissions : le sceau s\'incline vers le pointeur');
    const button = page.locator('[data-home="adm"] [data-home-magnet]').first();
    const b0 = await button.boundingBox();
    await page.mouse.move(b0.x + b0.width - 4, b0.y + b0.height - 4, { steps: 6 });
    await wait(700);
    const b1 = await button.boundingBox();
    check(Math.abs(b1.x - b0.x) > 3 || Math.abs(b1.y - b0.y) > 3, 'admissions : le bouton est attiré par le pointeur');
    await page.mouse.move(5, 5);

    // 4. La vie à l'UAZ : pile de cartes.
    const lifeTop = await top(page, '[data-home="life"] [data-home-stack]');
    await scrollTo(page, lifeTop - (await header(page)));
    await wait(1200);
    const cards = await page.locator('[data-home-card]').count();
    check(cards >= 2, `la pile compte ${cards} lieux`);
    check(await page.locator('[data-home-card-inner]').first().evaluate((el) => el.parentElement?.classList.contains('pin-spacer') && el.parentElement.parentElement?.matches('li')), 'les lieux sont épinglés sur grand écran, dans leur élément de liste');
    const scale0 = await page.locator('[data-home-card-inner]').first().evaluate((el) => new DOMMatrix(getComputedStyle(el).transform).a);
    await scrollTo(page, lifeTop - (await header(page)) + 800);
    await wait(900);
    const scale1 = await page.locator('[data-home-card-inner]').first().evaluate((el) => new DOMMatrix(getComputedStyle(el).transform).a);
    check(scale1 < scale0 - 0.03, `le premier lieu recule quand le suivant le recouvre (${scale0.toFixed(2)} → ${scale1.toFixed(2)})`);
    if (out) await page.screenshot({ path: path.join(out, 'accueil-vie.png') });
    check(await page.locator('[data-home-card-img]').evaluateAll((els) => els.length >= 1 && els.every((el) => (el.currentSrc || el.src).includes('/storage/'))), 'les lieux montrent de vraies photos de la médiathèque');

    // 5. Apprentissage pratique : le titre géant défile.
    await gotoSection(page, '[data-home="practice"]', -500);
    await wait(900);
    const belt0 = await page.locator('[data-home-belt]').evaluate((el) => getComputedStyle(el).transform);
    await scrollTo(page, (await top(page, '[data-home="practice"]')) + 100);
    await wait(900);
    check(belt0 !== await page.locator('[data-home-belt]').evaluate((el) => getComputedStyle(el).transform), 'pratique : le titre géant défile avec la page');

    // 8. Événements : billets et compte à rebours.
    await gotoSection(page, '[data-home="evt"]', -120);
    await wait(2200);
    check(await page.locator('[data-home-ticket]').count() === 3, 'événements : trois billets');
    check(await page.locator('[data-home-ticket]').evaluateAll((els) => els.every((el) => Number(getComputedStyle(el).opacity) > 0.95)), 'événements : les billets sont posés');
    const s1 = await page.locator('[data-unit="s"]').first().innerText();
    await wait(2300);
    check(await page.locator('[data-unit="s"]').first().innerText() !== s1, 'événements : le compte à rebours du premier billet avance');

    // 9. Ressources : l'image suit le pointeur.
    await gotoSection(page, '[data-home="res"]', 150);
    await wait(1400);
    const item = page.locator('[data-home-res-item]').first();
    const ib = await item.boundingBox();
    await page.mouse.move(ib.x + 300, ib.y + ib.height / 2, { steps: 6 });
    check(await until(async () => item.locator('[data-home-peek]').evaluate((el) => Number(getComputedStyle(el).opacity) > 0.9), 3000), 'ressources : l\'image du lieu apparaît au survol');
    const px0 = (await item.locator('[data-home-peek]').boundingBox()).x;
    await page.mouse.move(ib.x + 800, ib.y + ib.height / 2, { steps: 8 });
    await wait(800);
    check((await item.locator('[data-home-peek]').boundingBox()).x > px0 + 200, 'ressources : l\'image suit le pointeur');
    await page.mouse.move(5, 5);

    // 11. Fermeture.
    await gotoSection(page, '[data-home="close"]', -100);
    await wait(2200);
    check(await until(async () => (await page.locator('[data-home-close-title] .word').count()) >= 3, 3000), 'fermeture : le titre arrive mot à mot');
    await page.context().close();

    // ── Mobile ──
    const mobile = await open({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    await wait(2500);
    const height = await mobile.evaluate(() => document.documentElement.scrollHeight);
    for (let y = 0; y < height; y += 400) { await mobile.evaluate((v) => scrollTo(0, v), y); await wait(30); }
    await wait(1200);
    check((await mobile.evaluate(() => document.documentElement.scrollWidth)) <= 390, 'mobile : aucun défilement horizontal');
    check((await mobile.locator('.pin-spacer').count()) === 0, 'mobile : rien n\'est épinglé');
    check(await mobile.locator('[data-home-panel] .home-panel__more').evaluateAll((els) => els.length === 4 && els.every((el) => Number(getComputedStyle(el).opacity) === 1)), 'mobile : les domaines montrent tous leurs programmes');
    await mobile.context().close();

    // ── Mouvement réduit ──
    const calm = await open({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
    await wait(1200);
    check((await calm.locator('.pin-spacer').count()) === 0, 'mouvement réduit : rien n\'est épinglé');
    check(await calm.locator('[data-home-light] .word').count() === 0 && await calm.locator('[data-home-light]').evaluate((el) => Number(getComputedStyle(el).opacity) === 1), 'mouvement réduit : la phrase est entièrement lisible');
    await gotoSection(calm, '[data-home="dom"]', 120);
    await calm.locator('[data-home-panel]').nth(2).hover();
    check(await until(async () => calm.locator('[data-home-panel]').nth(2).evaluate((el) => el.classList.contains('is-open')), 2000), 'mouvement réduit : les domaines s\'ouvrent quand même au survol');
    check(await calm.locator('[data-home-ticket]').evaluateAll((els) => els.length === 3 && els.every((el) => Number(getComputedStyle(el).opacity) === 1)), 'mouvement réduit : les billets sont visibles');
    await calm.context().close();

    // ── Anglais ──
    const en = await open({ viewport: { width: 1440, height: 900 } }, '/en');
    check(await en.getAttribute('html', 'lang') === 'en', 'anglais : <html lang="en">');
    check((await en.locator('.home-diff .eyebrow').first().innerText()).toLowerCase().includes('uaz difference'), 'anglais : « The UAZ difference »');
    await en.context().close();

    check(errors.length === 0, `aucune erreur JavaScript${errors.length ? ' : ' + errors.join('; ') : ''}`);
} catch (error) {
    console.log(`✗ parcours interrompu : ${error.message.split('\n')[0]}`);
    failures++;
} finally {
    await browser.close();
    try { events('down'); } catch (error) { console.log(`✗ nettoyage impossible : ${error.message.split('\n')[0]}`); failures++; }
}

process.exit(failures ? 1 : 0);
