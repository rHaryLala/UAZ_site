// Capture du haut de page (première fenêtre d'affichage) pour comparer avec la maquette.
// Usage : node tests/e2e/top.mjs <fichier.png> [chemin=/] [largeur=1440] [hauteur=900]
import { chromium } from '@playwright/test';

const [out, route = '/', width = '1440', height = '900'] = process.argv.slice(2);
const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const browser = await chromium.launch();
const page = await (await browser.newContext({ viewport: { width: Number(width), height: Number(height) }, reducedMotion: 'reduce' })).newPage();
await page.goto(base + route, { waitUntil: 'networkidle' });
await page.waitForTimeout(600);
await page.screenshot({ path: out });
await browser.close();
