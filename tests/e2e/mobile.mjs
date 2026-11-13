// Retouche photo et Studio UAZ sur téléphone : l'aperçu reste FIXE en haut de l'écran pendant que les réglages défilent dessous, sans défilement de la
// page entière ni débordement sur le côté, en portrait comme en paysage. Usage : BASE_URL=http://127.0.0.1:8001 node tests/e2e/mobile.mjs <photo.jpg> [dossier-captures]
import fs from 'node:fs';
import path from 'node:path';
import { chromium, devices } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const photo = process.argv[2];
const out = process.argv[3];
if (out) fs.mkdirSync(out, { recursive: true });
let failures = 0;
const check = (ok, label) => { console.log(`${ok ? '✓' : '✗'} ${label}`); if (!ok) failures++; };
const until = async (fn, ms = 20000) => {
    const end = Date.now() + ms;
    while (Date.now() < end) { try { if (await fn()) return true; } catch { /* chargement */ } await new Promise((r) => setTimeout(r, 150)); }
    return false;
};

const browser = await chromium.launch();
try {
    const context = await browser.newContext({ ...devices['Pixel 7'] });
    const page = await context.newPage();
    for (let i = 0; i < 8; i++) { try { await page.goto(`${base}/admin/login`, { waitUntil: 'networkidle' }); await page.waitForFunction(() => window.Livewire, null, { timeout: 15000 }); break; } catch { await new Promise((r) => setTimeout(r, 2000)); } }
    await page.locator('input[type=email]').fill(process.env.ADMIN_EMAIL ?? 'admin@uaz.test');
    await page.locator('input[type=password]').fill(process.env.ADMIN_PASSWORD ?? 'password');
    await page.getByRole('button', { name: /Connexion|Se connecter/ }).click();
    await page.waitForURL('**/admin', { timeout: 30000 });

    const mesure = (selScene, selPanneau) => page.evaluate(([a, b]) => {
        const sc = document.querySelector(a).getBoundingClientRect(), pn = document.querySelector(b);
        return { top: Math.round(sc.top), h: Math.round(sc.height), vh: window.innerHeight, panneauDefile: pn.scrollHeight > pn.clientHeight + 4, panneauTop: pn.scrollTop,
            pageDefile: document.documentElement.scrollHeight > window.innerHeight + 2, debordeCote: document.documentElement.scrollWidth > window.innerWidth + 1 };
    }, [selScene, selPanneau]);

    // --- Retouche photo ---
    for (let i = 0; i < 6; i++) { try { await page.goto(`${base}/admin/retouche-photo`, { waitUntil: 'load' }); await page.waitForSelector('#rpApp', { timeout: 15000 }); break; } catch { await new Promise((r) => setTimeout(r, 2000)); } }
    await page.locator('#rpFichiers').setInputFiles(photo);
    check(await until(async () => (await page.locator('#rpVignettes li').count()) === 1), 'retouche : la photo est ouverte');
    await page.waitForTimeout(1500);
    const avant = await mesure('#rpScene', '#rpPanneau');
    check(avant.h > avant.vh * 0.25 && avant.h < avant.vh * 0.6, `retouche : l'aperçu occupe une part raisonnable de l'écran (${avant.h} px sur ${avant.vh})`);
    check(avant.panneauDefile && !avant.pageDefile && !avant.debordeCote, 'retouche : seuls les réglages défilent (pas la page, pas de débordement sur le côté)');
    if (out) await page.screenshot({ path: path.join(out, 'mobile-retouche-haut.png') });
    await page.locator('#rpPanneau').evaluate((el) => { el.scrollTop = el.scrollHeight; });
    await page.waitForTimeout(400);
    const apres = await mesure('#rpScene', '#rpPanneau');
    check(apres.top === avant.top && apres.h === avant.h && apres.panneauTop > 300, 'retouche : après avoir fait défiler les réglages, l\'aperçu n\'a pas bougé');
    if (out) await page.screenshot({ path: path.join(out, 'mobile-retouche-bas.png') });

    // --- Studio UAZ ---
    for (let i = 0; i < 6; i++) { try { await page.goto(`${base}/admin/studio`, { waitUntil: 'load' }); await page.waitForFunction(() => window.StudioEditeur, null, { timeout: 15000 }); break; } catch { await new Promise((r) => setTimeout(r, 2000)); } }
    await page.waitForTimeout(1200);
    const s1 = await mesure('#stScene', '#stModeles');
    check(s1.h > s1.vh * 0.3 && s1.top < s1.vh * 0.2, `studio : le visuel est en haut de l'écran (${s1.top} px du haut, ${s1.h} px de haut)`);
    check(s1.panneauDefile && !s1.pageDefile && !s1.debordeCote, 'studio : seul le panneau défile (pas la page, pas de débordement sur le côté)');
    const cadre = await page.evaluate(() => { const r = document.getElementById('stCadre').getBoundingClientRect(); return { gauche: Math.round(r.left), droite: Math.round(r.right), largeur: window.innerWidth }; });
    check(cadre.gauche >= 0 && cadre.droite <= cadre.largeur, `studio : le visuel tient dans la largeur de l’écran (${cadre.gauche} à ${cadre.droite} sur ${cadre.largeur} px)`);
    await page.locator('#stModeles').evaluate((el) => { el.scrollTop = el.scrollHeight; });
    await page.waitForTimeout(300);
    const s2 = await mesure('#stScene', '#stModeles');
    check(s2.top === s1.top && s2.h === s1.h, 'studio : le visuel ne bouge pas quand on fait défiler les modèles');
    await page.evaluate(() => document.fonts.ready);
    await page.waitForTimeout(800);
    if (out) await page.screenshot({ path: path.join(out, 'mobile-studio.png') });

    // --- Téléphone tenu en travers ---
    await page.setViewportSize({ width: 844, height: 390 });
    await page.waitForTimeout(600);
    const paysage = await page.evaluate(() => { const a = document.getElementById('stScene').getBoundingClientRect(), b = document.querySelector('.st-panneau').getBoundingClientRect(); return { sceneGauche: a.left < b.left, memeLigne: Math.abs(a.top - b.top) < 4 }; });
    check(paysage.sceneGauche && paysage.memeLigne, 'studio, en paysage : le visuel à gauche, les réglages à droite');
} finally {
    await browser.close();
}
process.exit(failures ? 1 : 0);
