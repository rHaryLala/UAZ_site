// Fenêtre « Newsletter » dans un vrai navigateur : ouverture à la première visite, pas de réouverture, réouverture après 20 jours,
// bouton « S'abonner » de la barre du haut, abonnement, puis plus jamais de fenêtre automatique.
// Prérequis : serveur lancé. Usage : node tests/e2e/newsletter.mjs [dossier-captures]
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const out = process.argv[2];
if (out) fs.mkdirSync(out, { recursive: true });
const php = process.env.PHP ?? 'php';
let failures = 0;
const check = (ok, label) => {
    console.log(`${ok ? '✓' : '✗'} ${label}`);
    if (!ok) failures++;
};

const email = `e2e-${Date.now()}@exemple.com`;
const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'fr-FR' });
const page = await context.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message.slice(0, 140)));
const dialog = page.locator('[data-newsletter-dialog]');
const isOpen = () => dialog.evaluate((d) => d.open);

try {
    // 1. Premier venu : la fenêtre s'ouvre toute seule.
    await page.goto(base, { waitUntil: 'networkidle' });
    await page.waitForFunction(() => document.querySelector('[data-newsletter-dialog]')?.open, null, { timeout: 15000 }).catch(() => {});
    check(await isOpen(), 'première visite : la fenêtre s\'ouvre toute seule');
    if (out) await page.screenshot({ path: path.join(out, 'newsletter-ouverte.png') });
    await dialog.getByRole('button', { name: 'Fermer' }).first().click();
    check(!(await isOpen()), 'le bouton Fermer la referme');

    // 2. Revenir tout de suite : pas de réouverture.
    await page.goto(base, { waitUntil: 'networkidle' });
    await page.waitForTimeout(7500);
    check(!(await isOpen()), 'deuxième visite immédiate : la fenêtre ne se rouvre pas');

    // 3. 21 jours plus tard (même navigateur) : elle revient.
    await page.evaluate(() => localStorage.setItem('uaz.newsletter', JSON.stringify({ seenAt: Date.now() - 21 * 86400000 })));
    await page.goto(base, { waitUntil: 'networkidle' });
    await page.waitForFunction(() => document.querySelector('[data-newsletter-dialog]')?.open, null, { timeout: 15000 }).catch(() => {});
    check(await isOpen(), 'après 21 jours sans abonnement : la fenêtre revient');
    await page.keyboard.press('Escape');

    // 4. 19 jours seulement : elle ne revient pas.
    await page.evaluate(() => localStorage.setItem('uaz.newsletter', JSON.stringify({ seenAt: Date.now() - 19 * 86400000 })));
    await page.goto(base, { waitUntil: 'networkidle' });
    await page.waitForTimeout(7500);
    check(!(await isOpen()), 'après 19 jours : pas encore');

    // 5. Bouton « S'abonner » de la barre du haut, placé après Yearbook.
    const topbar = page.locator('.topbar nav');
    const labels = await topbar.locator('a, button').allInnerTexts();
    const iYear = labels.findIndex((t) => t.trim() === 'Yearbook');
    const iSub = labels.findIndex((t) => t.trim() === 'S\'abonner');
    check(iYear >= 0 && iSub === iYear + 1, `« S'abonner » suit « Yearbook » dans la barre du haut (${labels.map((t) => t.trim()).join(' | ')})`);
    await topbar.getByRole('button', { name: 'S\'abonner' }).click();
    check(await isOpen(), 'le bouton de la barre du haut ouvre la fenêtre');

    // 6. Adresse invalide, puis abonnement.
    await dialog.getByLabel('Adresse e-mail').fill('pas-une-adresse');
    await dialog.getByRole('button', { name: 'S\'abonner' }).click();
    await page.waitForFunction(() => !document.querySelector('[data-newsletter-error]').hidden, null, { timeout: 8000 });
    check(true, 'une adresse invalide affiche un message d\'erreur');
    await dialog.getByLabel('Adresse e-mail').fill(email);
    await dialog.getByRole('button', { name: 'S\'abonner' }).click();
    await page.waitForFunction(() => !document.querySelector('[data-newsletter-done]').hidden, null, { timeout: 15000 });
    check((await dialog.locator('[data-newsletter-done]').innerText()).includes('Vous êtes abonné(e)'), 'l\'abonnement est immédiat : la fenêtre confirme « Vous êtes abonné(e) », sans parler d\'e-mail de confirmation');
    check(!(await dialog.innerText()).toLowerCase().includes('e-mail de confirmation'), 'aucune mention d\'un e-mail de confirmation dans la fenêtre');
    if (out) await page.screenshot({ path: path.join(out, 'newsletter-envoyee.png') });
    const stored = await page.evaluate(() => JSON.parse(localStorage.getItem('uaz.newsletter')));
    check(stored.subscribed === true, 'l\'état « abonné » est mémorisé dans le navigateur');
    await page.keyboard.press('Escape');

    // 7. Abonné : même 30 jours plus tard, plus de fenêtre automatique.
    await page.evaluate(() => localStorage.setItem('uaz.newsletter', JSON.stringify({ subscribed: true, seenAt: Date.now() - 30 * 86400000 })));
    await page.goto(base, { waitUntil: 'networkidle' });
    await page.waitForTimeout(7500);
    check(!(await isOpen()), 'abonné : la fenêtre ne revient jamais toute seule');

    // 8. Mobile : le bouton du menu plein écran ouvre aussi la fenêtre.
    await page.setViewportSize({ width: 390, height: 800 });
    await page.goto(base, { waitUntil: 'networkidle' });
    await page.getByRole('button', { name: /Menu/ }).first().click();
    await page.waitForTimeout(1200);
    await page.locator('.menu-overlay [data-newsletter-open]').first().click({ timeout: 8000 });
    check(await isOpen(), 'mobile : « S\'abonner » du menu plein écran ouvre la fenêtre');
    if (out) await page.screenshot({ path: path.join(out, 'newsletter-mobile.png') });
    check(errors.length === 0, `aucune erreur JavaScript${errors.length ? ' : ' + errors.join('; ') : ''}`);
} catch (error) {
    console.log(`✗ parcours interrompu : ${error.message.split('\n')[0]}`);
    if (out) await page.screenshot({ path: path.join(out, 'newsletter-erreur.png') });
    failures++;
} finally {
    await browser.close();
    execFileSync(php, ['artisan', 'tinker', '--execute', `App\\Models\\NewsletterSubscriber::where('email','${email}')->delete();`], { encoding: 'utf8' });
}

process.exit(failures ? 1 : 0);
