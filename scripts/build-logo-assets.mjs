// Prépare les déclinaisons optimisées du sceau (docs/assets/UAZLogo.png) pour le site :
// WebP/PNG en plusieurs tailles, favicons, icône tactile. Les originaux ne sont jamais modifiés ni déformés.
// Usage : node scripts/build-logo-assets.mjs
import fs from 'node:fs';
import sharp from 'sharp';

const SRC = 'docs/assets/UAZLogo.png';
const OUT = 'public/images';
fs.mkdirSync(OUT, { recursive: true });

// Le sceau a des bords ondulés et un fond transparent : on le rogne au plus juste, sans déformation.
const trimmed = await sharp(SRC).trim({ threshold: 5 }).toBuffer();

for (const size of [96, 192, 384, 768]) {
    await sharp(trimmed).resize(size, size, { fit: 'inside' }).webp({ quality: 88, alphaQuality: 100 }).toFile(`${OUT}/uaz-seal-${size}.webp`);
}
await sharp(trimmed).resize(192, 192, { fit: 'inside' }).png({ compressionLevel: 9 }).toFile(`${OUT}/uaz-seal-192.png`);

// Favicons : le sceau sur transparent (PNG) ; apple-touch-icon sur fond blanc.
await sharp(trimmed).resize(32, 32, { fit: 'inside' }).png().toFile('public/favicon-32.png');
await sharp(trimmed).resize(192, 192, { fit: 'inside' }).png().toFile('public/icon-192.png');
await sharp(trimmed)
    .resize(150, 150, { fit: 'inside' })
    .extend({ top: 15, bottom: 15, left: 15, right: 15, background: '#ffffff' })
    .flatten({ background: '#ffffff' })
    .png()
    .toFile('public/apple-touch-icon.png');

for (const f of fs.readdirSync(OUT)) console.log(f.padEnd(24), (fs.statSync(`${OUT}/${f}`).size / 1024).toFixed(1) + ' Ko');
