// Fluidité de la vidéo du héros pendant un défilement continu : combien d'images distinctes sont réellement affichées et quel est
// le plus long arrêt. Usage : node tests/e2e/library-smooth.mjs [url-de-la-page]
import { chromium } from '@playwright/test';

const url = process.argv[2] ?? 'http://127.0.0.1:8000/vie-etudiante/bibliotheque';
const browser = await chromium.launch();
const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
await page.addInitScript(() => localStorage.setItem('uaz.newsletter', JSON.stringify({ subscribed: true })));
await page.goto(url, { waitUntil: 'load' });
await page.waitForFunction(() => document.querySelector('[data-lib-video]')?.readyState >= 3, null, { timeout: 30000 });
await page.waitForTimeout(800);

// Journal des images effectivement présentées par le navigateur.
await page.evaluate(() => {
    const v = document.querySelector('[data-lib-video]');
    window.__frames = [];
    const loop = (now, meta) => {
        window.__frames.push({ t: now, media: meta.mediaTime });
        v.requestVideoFrameCallback(loop);
    };
    v.requestVideoFrameCallback(loop);
});

// Défilement continu de type molette : 2 800 px en 4 secondes (≈ 15 s de vidéo).
const start = Date.now();
for (let i = 0; i < 100; i++) {
    await page.mouse.wheel(0, 28);
    await page.waitForTimeout(40);
}
await page.waitForTimeout(600);
const seconds = (Date.now() - start) / 1000;

const result = await page.evaluate(() => {
    const f = window.__frames;
    const gaps = f.slice(1).map((x, i) => x.t - f[i].t);
    const media = f.map((x) => x.media);
    const steps = media.slice(1).map((m, i) => Math.abs(m - media[i]));
    return {
        presentees: f.length,
        distinctes: new Set(media.map((m) => m.toFixed(3))).size,
        arretMaxMs: Math.round(Math.max(0, ...gaps)),
        intervalleMedianMs: Math.round([...gaps].sort((a, b) => a - b)[Math.floor(gaps.length / 2)]),
        intervalleP95Ms: Math.round([...gaps].sort((a, b) => a - b)[Math.floor(gaps.length * 0.95)]),
        arretsSup80ms: gaps.filter((g) => g > 80).length,
        pasMaxS: +Math.max(0, ...steps).toFixed(2),
        position: +media[media.length - 1]?.toFixed(1),
    };
});
console.log({ dureeS: +seconds.toFixed(1), ...result, imagesParSeconde: +(result.presentees / seconds).toFixed(1) });
await browser.close();
