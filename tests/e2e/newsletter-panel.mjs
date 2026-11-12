// Panneau de l'éditeur de newsletters : largeur réglable (glisser la poignée, flèches, double-clic), largeur retenue après rechargement,
// et sélecteurs à plusieurs choix qui ne débordent pas (« Texte » de la photo en fond). Usage : node tests/e2e/newsletter-panel.mjs [dossier-captures]
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const out = process.argv[2];
if (out) fs.mkdirSync(out, { recursive: true });
const fixture = path.join(path.dirname(fileURLToPath(import.meta.url)), 'fixtures', 'newsletter.php');
const php = (...args) => execFileSync(process.env.PHP_BIN ?? 'php', args, { encoding: 'utf8' });
let failures = 0;
const check = (ok, label) => { console.log(`${ok ? '✓' : '✗'} ${label}`); if (!ok) failures++; };
const until = async (fn, ms = 10000) => {
    const end = Date.now() + ms;
    while (Date.now() < end) { try { if (await fn()) return true; } catch { /* chargement */ } await new Promise((r) => setTimeout(r, 120)); }
    return false;
};

const id = Number(php(fixture, 'up').trim().split('\n').pop());
const browser = await chromium.launch();
const errors = [];
try {
    const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
    page.on('pageerror', (e) => errors.push(e.message.slice(0, 140)));
    await page.goto(`${base}/admin/login`, { waitUntil: 'networkidle' });
    await page.waitForFunction(() => window.Livewire);
    await page.locator('input[type=email]').fill(process.env.ADMIN_EMAIL ?? 'admin@uaz.test');
    await page.locator('input[type=password]').fill(process.env.ADMIN_PASSWORD ?? 'password');
    await page.getByRole('button', { name: /Connexion|Se connecter/ }).click();
    await page.waitForURL('**/admin');
    await page.goto(`${base}/admin/newsletter-editor/${id}`, { waitUntil: 'load' });
    await page.evaluate(() => localStorage.removeItem('nl-panel-width'));
    await page.reload({ waitUntil: 'load' });

    const width = () => page.locator('.nl-panel').evaluate((el) => Math.round(el.getBoundingClientRect().width));
    const handle = page.locator('[data-nl-resizer]');
    const start = await width();
    check(start > 300 && start < 400, `largeur d'origine raisonnable (${start} px)`);

    // Étirer : on glisse la poignée vers la droite.
    let box = await handle.boundingBox();
    await page.mouse.move(box.x + box.width / 2, box.y + 200);
    await page.mouse.down();
    await page.mouse.move(box.x + 200, box.y + 200, { steps: 6 });
    await page.mouse.up();
    const wide = await width();
    check(wide > start + 120, `le panneau s'étire (${start} → ${wide} px)`);
    const frameLeft = await page.locator('[data-nl-frame-wrap]').evaluate((el) => Math.round(el.getBoundingClientRect().left));
    check(frameLeft >= wide - 2, 'le plan de travail se décale d\'autant');

    // Réduire, jusqu'au minimum.
    box = await handle.boundingBox();
    await page.mouse.move(box.x + box.width / 2, box.y + 200);
    await page.mouse.down();
    await page.mouse.move(20, box.y + 200, { steps: 8 });
    await page.mouse.up();
    const narrow = await width();
    check(narrow >= 238 && narrow <= 250, `le panneau se réduit, sans passer sous le minimum (${narrow} px)`);

    // Flèches, puis largeur retenue après rechargement, puis double-clic.
    await handle.focus();
    await page.keyboard.press('ArrowRight');
    await page.keyboard.press('ArrowRight');
    const keyed = await width();
    check(keyed === narrow + 40, `les flèches règlent la largeur (${narrow} → ${keyed} px)`);
    await page.reload({ waitUntil: 'load' });
    check(Math.abs((await width()) - keyed) <= 1, 'la largeur choisie est retenue après rechargement');
    await handle.dblclick();
    check(Math.abs((await width()) - start) <= 1, 'un double-clic rend la taille d\'origine');

    // Le sélecteur « Texte » de la photo en fond ne déborde plus, même dans un panneau étroit.
    await page.evaluate(() => localStorage.setItem('nl-panel-width', '260'));
    await page.reload({ waitUntil: 'load' });
    await page.locator('[data-nl-tab="add"]').click();
    await page.locator('.nl-card', { hasText: 'Photo en fond' }).first().click();
    await until(async () => (await page.locator('.nl-seg').count()) > 0);
    const overflow = await page.locator('.nl-seg').evaluateAll((els) => els.filter((el) => el.scrollWidth > el.clientWidth + 1 || [...el.children].some((b) => b.getBoundingClientRect().right > el.getBoundingClientRect().right + 1)).length);
    check(overflow === 0, 'aucun sélecteur ne déborde, même à 260 px');
    if (out) await page.screenshot({ path: path.join(out, 'panel-narrow.png') });
    await page.evaluate(() => localStorage.removeItem('nl-panel-width'));
    check(errors.length === 0, 'aucune erreur JavaScript' + (errors.length ? ' : ' + errors.join(' | ') : ''));
} finally {
    await browser.close();
    php(fixture, 'down');
}
process.exit(failures ? 1 : 0);
