// Back-office : captures et contrôles aux largeurs bureau (1440), tablette (768) et mobile (390), en clair et en mode sombre.
// Vérifie qu'aucune page ne déborde horizontalement et que les écrans clés s'affichent. Usage : node tests/e2e/admin-responsive.mjs [dossier-captures]
import path from 'node:path';
import fs from 'node:fs';
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const out = process.argv[2];
if (out) fs.mkdirSync(out, { recursive: true });
let failures = 0;
const check = (ok, label) => {
    console.log(`${ok ? '✓' : '✗'} ${label}`);
    if (!ok) failures++;
};

const screens = [
    ['tableau-de-bord', '/admin'],
    ['actualites', '/admin/news-posts'],
    ['actualite-edition', '/admin/news-posts/create'],
    ['equipe', '/admin/team-members'],
    ['mediatheque', '/admin/media-assets'],
    ['messages', '/admin/contact-messages'],
];

const browser = await chromium.launch();
for (const [label, width, height, scheme] of [['bureau', 1440, 900, 'light'], ['tablette', 768, 1024, 'light'], ['mobile', 390, 844, 'light'], ['bureau-sombre', 1440, 900, 'dark']]) {
    const context = await browser.newContext({ viewport: { width, height }, locale: 'fr-FR', colorScheme: scheme });
    const page = await context.newPage();
    await page.goto(`${base}/admin/login`, { waitUntil: 'networkidle' });
    await page.waitForFunction(() => window.Livewire);
    await page.locator('input[type=email]').fill(process.env.ADMIN_EMAIL ?? 'admin@uaz.test');
    await page.locator('input[type=password]').fill(process.env.ADMIN_PASSWORD ?? 'password');
    await page.getByRole('button', { name: 'Connexion' }).click();
    await page.waitForURL('**/admin', { timeout: 20000 });

    for (const [name, url] of screens) {
        await page.goto(base + url, { waitUntil: 'networkidle' });
        await page.waitForTimeout(1800);
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        check(overflow <= 2, `${label} · ${name} : pas de défilement horizontal (${overflow} px)`);
        if (out) await page.screenshot({ path: path.join(out, `${label}-${name}.png`), timeout: 60000 });
    }
    await context.close();
}
await browser.close();
process.exit(failures ? 1 : 0);
