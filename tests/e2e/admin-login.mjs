// Connexion au back-office dans un vrai navigateur : vitrine de vraies photos, « regard » qui suit le pointeur, cligne à l'entrée d'un champ, s'ouvre avec
// la saisie, se ferme quand le mot de passe est visible, photos secouées sur une erreur, connexion réussie qui démonte la scène, mobile, mouvement réduit.
// Crée un compte temporaire, supprimé à la fin. Prérequis : serveur lancé, base de développement migrée (avec des photos publiées).
// Usage : node tests/e2e/admin-login.mjs [dossier-captures]
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const php = process.env.PHP ?? 'php';
const out = process.argv[2];
if (out) fs.mkdirSync(out, { recursive: true });
const tinker = (code) => execFileSync(php, ['artisan', 'tinker', '--execute', code], { encoding: 'utf8' }).trim();
const email = `connexion-${Date.now()}@example.test`;
const password = 'Connexion-test-2026';
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
/** Variable CSS numérique du regard (--lx, --lry, --shut…) posée sur la scène. */
const v = (page, name) => page.evaluate((n) => parseFloat(document.querySelector('[data-uaz-scene]').style.getPropertyValue(`--${n}`)) || 0, name);
/**
 * Relevé fait dans la page à chaque image pendant `ms` : pour voir un clignement ou une secousse qui ne dure qu'un instant.
 * Renvoie une promesse du plus petit et du plus grand écart relevés, et de la dernière valeur.
 */
const watch = (page, ms, kind) =>
    page.evaluate(
        ([duration, what]) =>
            new Promise((resolve) => {
                const scene = document.querySelector('[data-uaz-scene]');
                const read = () => (what === 'lry' ? parseFloat(scene.style.getPropertyValue('--lry')) : Math.abs(new DOMMatrix(getComputedStyle(document.querySelector('[data-uaz-gallery="lit"]')).transform).m41));
                let min = Infinity;
                let max = -Infinity;
                let last = 0;
                const end = performance.now() + duration;
                const tick = () => {
                    last = read();
                    min = Math.min(min, last);
                    max = Math.max(max, last);
                    if (performance.now() < end) requestAnimationFrame(tick);
                    else resolve({ min, max, last });
                };
                tick();
            }),
        [ms, kind],
    );

tinker(`$u = App\\Models\\User::create(['name' => 'Connexion test', 'email' => '${email}', 'password' => '${password}']); $u->assignRole('editor');`);

const browser = await chromium.launch();
const errors = [];
const open = async (options, url = '/admin/login') => {
    const context = await browser.newContext({ locale: 'fr-FR', ...options });
    const page = await context.newPage();
    page.on('pageerror', (e) => errors.push(e.message.slice(0, 140)));
    await page.goto(base + url, { waitUntil: 'networkidle', timeout: 90000 });
    await page.waitForFunction(() => window.Livewire);

    return page;
};

try {
    // ── Ordinateur ──
    const page = await open({ viewport: { width: 1440, height: 900 } });
    check(await page.locator('[data-uaz-gallery]').count() === 2, 'la vitrine a deux couches : photos éteintes et photos éclairées');
    check(await page.locator('[data-uaz-gallery="base"] [data-uaz-tile]').count() >= 3, 'au moins trois photos de l\'université');
    check(await page.locator('[data-uaz-gallery="base"] img').evaluateAll((els) => els.every((el) => el.currentSrc.includes('/storage/') || el.src.includes('/storage/'))), 'ce sont de vraies photos de la médiathèque du site');
    check(await until(async () => (await page.locator('.fi-simple-header-heading .word').count()) >= 3, 5000), 'le titre se découpe en mots');
    await wait(3200);
    check(await page.getByRole('button', { name: 'Connexion' }).isVisible(), 'le bouton « Connexion » est visible');
    if (out) await page.screenshot({ path: path.join(out, 'connexion-repos.png') });

    // Le regard suit le pointeur.
    await page.mouse.move(200, 250, { steps: 8 });
    check(await until(async () => Math.abs((await v(page, 'lx')) - 200) < 25 && Math.abs((await v(page, 'ly')) - 250) < 25, 4000), 'le regard suit le pointeur (haut gauche)');
    const pxLeft = await v(page, 'px');
    await page.mouse.move(650, 700, { steps: 8 });
    check(await until(async () => Math.abs((await v(page, 'lx')) - 650) < 25 && Math.abs((await v(page, 'ly')) - 700) < 25, 4000), 'le regard suit le pointeur (bas droite)');
    check((await v(page, 'px')) < pxLeft, 'les photos s\'inclinent vers le pointeur');
    check(await page.locator('[data-uaz-lens]').evaluate((el) => { const r = el.getBoundingClientRect(); return r.left < 650 && r.right > 650; }), 'l\'anneau du regard accompagne le pointeur');
    if (out) await page.screenshot({ path: path.join(out, 'connexion-regard.png') });

    // Entrer dans un champ : clignement.
    await wait(1500);
    const blinking = watch(page, 800, 'lry');
    await page.locator('input[type=email]').click();
    const lry = await blinking;
    check(lry.min < 0.3 && lry.last > 0.85, `entrer dans un champ : le regard cligne (ouverture mini ${lry.min.toFixed(2)}, puis rouvert)`);

    // La saisie ouvre le regard.
    const before = await v(page, 'lr');
    await page.locator('input[type=email]').fill(email);
    await page.locator('input[type=password]').fill('mot-de-passe-faux');
    check(await until(async () => (await v(page, 'lr')) > before * 1.2, 4000), 'le formulaire se remplit : le regard s\'ouvre plus grand');

    // Mot de passe visible : le regard se ferme, les photos s'estompent.
    await page.getByRole('button', { name: 'Montrer le mot de passe' }).click();
    check(await until(async () => (await v(page, 'lry')) < 0.1 && (await v(page, 'shut')) > 0.9, 3000), 'mot de passe visible : le regard se ferme et les photos s\'estompent');
    const frozen = await v(page, 'lx');
    await page.mouse.move(100, 100, { steps: 5 });
    await wait(500);
    check(Math.abs((await v(page, 'lx')) - frozen) < 2, 'mot de passe visible : le regard ne suit plus le pointeur');
    if (out) await page.screenshot({ path: path.join(out, 'connexion-ferme.png') });
    await page.getByRole('button', { name: 'Masquer le mot de passe' }).click();
    check(await until(async () => (await v(page, 'lry')) > 0.9 && (await v(page, 'shut')) < 0.1, 3000), 'mot de passe masqué : le regard se rouvre');

    // Mauvais mot de passe : photos secouées.
    const shake = watch(page, 5000, 'x');
    await page.getByRole('button', { name: 'Connexion' }).click();
    check(await until(async () => (await page.locator('.fi-fo-field-wrp-error-message').count()) >= 1, 8000), 'mauvais mot de passe : Filament affiche l\'erreur');
    check((await shake).max > 5, 'erreur : les photos se secouent');

    // Bon mot de passe : on entre, la scène est démontée.
    await page.locator('input[type=password]').fill(password);
    await page.getByRole('button', { name: 'Connexion' }).click();
    await page.waitForURL('**/admin', { timeout: 20000 });
    check(true, 'bon mot de passe : on arrive au tableau de bord');
    check(await until(async () => (await page.locator('[data-uaz-scene]').count()) === 0, 5000), 'la scène est démontée après la connexion');
    check(await page.locator('.fi-sidebar, .fi-topbar').first().isVisible(), 'le tableau de bord n\'est pas touché par les styles de connexion');
    await page.context().close();

    // ── Mot de passe oublié ──
    const reset = await open({ viewport: { width: 1440, height: 900 } }, '/admin/password-reset/request');
    check(await reset.locator('[data-uaz-scene]').count() === 1, 'mot de passe oublié : la même vitrine');
    await reset.context().close();

    // ── Mobile ──
    const mobile = await open({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    await wait(2800);
    check((await mobile.evaluate(() => document.documentElement.scrollWidth)) <= 390, 'mobile : aucun défilement horizontal');
    const banner = await mobile.locator('[data-uaz-scene]').boundingBox();
    check(banner.width >= 390 && banner.height > 180 && banner.height < 300, `mobile : la vitrine est un bandeau en haut (${Math.round(banner.height)} px)`);
    check(await mobile.getByRole('button', { name: 'Connexion' }).isVisible(), 'mobile : le bouton « Connexion » est visible');
    const lx0 = await v(mobile, 'lx');
    await wait(4500);
    check(Math.abs((await v(mobile, 'lx')) - lx0) > 5, 'mobile : sans souris, le regard dérive doucement');
    if (out) await mobile.screenshot({ path: path.join(out, 'connexion-mobile.png') });
    await mobile.context().close();

    // ── Mouvement réduit ──
    const calm = await open({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
    await wait(800);
    check(await calm.locator('.fi-simple-header-heading .word').count() === 0, 'mouvement réduit : le titre n\'est pas découpé');
    check(await calm.getByRole('button', { name: 'Connexion' }).isVisible(), 'mouvement réduit : le formulaire est complet');
    const fixed = await v(calm, 'lx');
    await calm.mouse.move(120, 120, { steps: 6 });
    await wait(600);
    check(Math.abs((await v(calm, 'lx')) - fixed) < 1, 'mouvement réduit : le regard reste fixe');
    await calm.locator('input[type=password]').fill('x');
    await calm.getByRole('button', { name: 'Montrer le mot de passe' }).click();
    check(await until(async () => (await v(calm, 'lry')) < 0.1, 1500), 'mouvement réduit : le regard se ferme quand même avec le mot de passe visible');
    await calm.context().close();

    check(errors.length === 0, `aucune erreur JavaScript${errors.length ? ' : ' + errors.join('; ') : ''}`);
} catch (error) {
    console.log(`✗ parcours interrompu : ${error.message.split('\n')[0]}`);
    failures++;
} finally {
    await browser.close();
    tinker(`App\\Models\\User::where('email','${email}')->delete();`);
}

process.exit(failures ? 1 : 0);
