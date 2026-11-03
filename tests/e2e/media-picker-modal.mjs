// Sélecteur de médias dans une fenêtre modale de Filament (Témoignages) : le champ de texte alternatif reçoit la frappe
// (le piège à focus de la modale est suspendu pendant que le sélecteur est ouvert, puis rétabli), l'envoi et le choix
// aboutissent. Compte de développement : admin@uaz.test. Usage : node tests/e2e/media-picker-modal.mjs
import { execFileSync } from 'node:child_process';
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const alt = 'E2E modale étudiante souriante';
let failures = 0;
const check = (ok, label) => {
    console.log(`${ok ? '✓' : '✗'} ${label}`);
    if (!ok) failures++;
};
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { encoding: 'utf8' });

const browser = await chromium.launch();
const errors = [];
try {
    const page = await (await browser.newContext({ viewport: { width: 1360, height: 860 } })).newPage();
    page.on('pageerror', (e) => errors.push(e.message.slice(0, 140)));
    await page.goto(`${base}/admin/login`, { waitUntil: 'networkidle' });
    await page.waitForFunction(() => window.Livewire);
    await page.locator('input[type=email]').fill(process.env.ADMIN_EMAIL ?? 'admin@uaz.test');
    await page.locator('input[type=password]').fill(process.env.ADMIN_PASSWORD ?? 'password');
    await page.getByRole('button', { name: /Connexion|Se connecter/ }).click();
    await page.waitForURL('**/admin');

    await page.goto(`${base}/admin/testimonials`, { waitUntil: 'networkidle' });
    await page.getByRole('button', { name: /Nouveau|Créer/ }).first().click();
    await page.locator('.fi-modal-window').first().waitFor();
    await page.getByRole('button', { name: /Choisir une image/ }).first().click();
    await page.locator('.uaz-mp-tabs button', { hasText: 'Envoyer' }).click();
    await page.locator('.uaz-mp-drop input[type=file]').setInputFiles('public/favicon-32.png');

    const input = page.locator('.uaz-mp-pending input[type=text]').first();
    await input.click();
    await page.keyboard.type(alt, { delay: 15 });
    check((await input.inputValue()) === alt, 'modale : le texte alternatif se saisit');
    check(await page.evaluate(() => document.activeElement?.classList.contains('uaz-mp-input')), 'modale : le curseur reste dans le champ');

    const send = page.getByRole('button', { name: 'Envoyer dans la médiathèque' });
    check(await send.isEnabled(), 'modale : « Envoyer » est actif une fois le texte saisi');
    const sent = page.waitForResponse((r) => r.url().includes('media-browser/upload'), { timeout: 60000 });
    await send.click();
    check((await sent).status() === 201, 'modale : l\'envoi aboutit (201)');
    await page.locator('.uaz-mp-tile.is-selected').first().waitFor({ timeout: 30000 });
    await page.getByRole('button', { name: 'Choisir cette image' }).click();
    check(await page.locator('.uaz-mp-overlay').isHidden(), 'modale : le sélecteur se referme');
    check(await page.locator('.fi-modal-window img, .fi-modal-window [x-text*="current"]').count() > 0 || (await page.getByRole('button', { name: /Changer|Retirer/ }).count()) > 0, 'modale : l\'image choisie apparaît dans le formulaire');

    // Le piège à focus de la modale est rétabli : la touche Tab ne sort pas de la fenêtre.
    let inside = true;
    for (let i = 0; i < 12; i++) {
        await page.keyboard.press('Tab');
        inside &&= await page.evaluate(() => !!document.activeElement?.closest('.fi-modal-window'));
    }
    check(inside, 'modale : le piège à focus est rétabli après le choix');
    check(errors.length === 0, `aucune erreur JavaScript${errors.length ? ` : ${errors.join('; ')}` : ''}`);
} catch (error) {
    console.log(`✗ parcours interrompu : ${error.message.split('\n')[0]}`);
    failures++;
} finally {
    await browser.close();
    try { tinker(`App\\Models\\MediaAsset::where('alt', '${alt}')->get()->each->delete();`); } catch { /* nettoyage au mieux */ }
}

process.exit(failures ? 1 : 0);
