// Captures et contrôles visuels : mobile 390 px, tablette 768 px, bureau 1440 px.
// Usage : node tests/e2e/shots.mjs <dossier-sortie> <chemin> [<chemin>…] [--base=http://127.0.0.1:8000] [--only=390,1440] [--reduced]
// Pour chaque page : capture pleine page (après défilement pour déclencher les apparitions), erreurs console,
// requêtes en échec et détection de défilement horizontal.
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from '@playwright/test';

const args = process.argv.slice(2);
const flags = Object.fromEntries(args.filter((a) => a.startsWith('--')).map((a) => a.replace(/^--/, '').split('=')));
const [outDir, ...paths] = args.filter((a) => !a.startsWith('--'));
const base = flags.base ?? 'http://127.0.0.1:8000';
const widths = (flags.only ?? '390,768,1440').split(',').map(Number);
const heights = { 390: 844, 768: 1024, 1440: 900 };

fs.mkdirSync(outDir, { recursive: true });
const browser = await chromium.launch();
let problems = 0;

for (const p of paths) {
    for (const width of widths) {
        const context = await browser.newContext({
            viewport: { width, height: heights[width] ?? 900 },
            reducedMotion: flags.reduced ? 'reduce' : 'no-preference',
            deviceScaleFactor: 1,
        });
        const page = await context.newPage();
        const errors = [];
        page.on('console', (m) => m.type() === 'error' && errors.push(m.text()));
        page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`));
        page.on('requestfailed', (r) => errors.push(`requestfailed: ${r.url()}`));

        const response = await page.goto(base + p, { waitUntil: 'networkidle' });
        await page.evaluate(() => document.fonts.ready);

        // Défilement progressif : déclenche les apparitions et le chargement paresseux.
        const total = await page.evaluate(() => document.documentElement.scrollHeight);
        for (let y = 0; y < total; y += 500) {
            await page.evaluate((top) => window.scrollTo(0, top), y);
            await page.waitForTimeout(120);
        }
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.waitForTimeout(900);

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        const name = `${p.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '') || 'home'}-${width}.png`;
        await page.screenshot({ path: path.join(outDir, name), fullPage: true });

        const status = response?.status();
        const issues = [...errors, ...(overflow > 1 ? [`défilement horizontal de ${overflow}px`] : []), ...(status !== 200 ? [`HTTP ${status}`] : [])];
        problems += issues.length;
        console.log(`${issues.length ? '✗' : '✓'} ${p} @${width} -> ${name}${issues.length ? '\n    ' + issues.join('\n    ') : ''}`);
        await context.close();
    }
}

await browser.close();
process.exit(problems ? 1 : 0);
