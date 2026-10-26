// Extrait les couleurs dominantes du logo (docs/assets/UAZLogo.png) pour définir les tokens de marque.
// Usage : node scripts/extract-logo-colors.mjs [chemin-image]
import sharp from 'sharp';

const file = process.argv[2] ?? 'docs/assets/UAZLogo.png';
const { data, info } = await sharp(file)
    .resize(400, 400, { fit: 'inside' })
    .ensureAlpha()
    .raw()
    .toBuffer({ resolveWithObject: true });

// Quantification : 5 bits par canal, pixels opaques uniquement.
const buckets = new Map();
let total = 0;
for (let i = 0; i < data.length; i += 4) {
    if (data[i + 3] < 250) continue;
    const key = ((data[i] >> 3) << 10) | ((data[i + 1] >> 3) << 5) | (data[i + 2] >> 3);
    const b = buckets.get(key) ?? { n: 0, r: 0, g: 0, b: 0 };
    b.n++;
    b.r += data[i];
    b.g += data[i + 1];
    b.b += data[i + 2];
    buckets.set(key, b);
    total++;
}

const hex = (v) => Math.round(v).toString(16).padStart(2, '0');
const luminance = ([r, g, b]) => {
    const f = (c) => ((c /= 255) <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4);
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
};

const colors = [...buckets.values()]
    .sort((a, b) => b.n - a.n)
    .slice(0, 14)
    .map((b) => {
        const rgb = [b.r / b.n, b.g / b.n, b.b / b.n];
        return { hex: '#' + rgb.map(hex).join(''), share: ((b.n / total) * 100).toFixed(1) + '%', luminance: luminance(rgb).toFixed(3) };
    });

console.log(`${file} — ${info.width}x${info.height}, ${total} pixels opaques`);
console.table(colors);
