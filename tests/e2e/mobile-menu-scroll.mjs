// Menu plein écran sur petit écran : il doit défiler à la molette, au pavé tactile et au doigt jusqu'à la dernière rubrique (S'inscrire, adresse,
// réseaux sociaux). Lenis suspend le défilement de la page quand le menu s'ouvre et bloque alors la molette partout, sauf dans les zones marquées
// « data-lenis-prevent ». Usage : BASE_URL=http://127.0.0.1:8001 node tests/e2e/mobile-menu-scroll.mjs
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
let failures = 0;
const check = (ok, label) => { console.log(`${ok ? '✓' : '✗'} ${label}`); if (!ok) failures++; };
const browser = await chromium.launch();
try {
    for (const [name, mobile] of [['ordinateur (fenêtre étroite)', false], ['téléphone', true]]) {
        const page = await (await browser.newContext({ viewport: { width: 397, height: 678 }, isMobile: mobile, hasTouch: mobile })).newPage();
        await page.goto(`${base}/`, { waitUntil: 'networkidle' });
        await page.locator('[aria-controls="site-menu"]').click();
        await page.waitForTimeout(2500);
        const size = await page.evaluate(() => { const o = document.getElementById('site-menu'); return [o.scrollHeight, o.clientHeight]; });
        check(size[0] > size[1], `${name} : le menu est plus haut que l'écran (${size[0]} > ${size[1]} px)`);
        await page.mouse.move(200, 400);
        await page.mouse.wheel(0, 3000);
        await page.waitForTimeout(700);
        const scrolled = await page.evaluate(() => document.getElementById('site-menu').scrollTop);
        check(scrolled > 300, `${name} : la molette fait défiler le menu (${Math.round(scrolled)} px)`);
        const last = await page.evaluate(() => { const link = [...document.querySelectorAll('#site-menu address, #site-menu ul')].pop(); const r = link.getBoundingClientRect(); return r.bottom <= window.innerHeight + 1; });
        check(last, `${name} : la dernière rubrique (réseaux sociaux) est atteignable`);
        await page.close();
    }
} finally {
    await browser.close();
}
process.exit(failures ? 1 : 0);
