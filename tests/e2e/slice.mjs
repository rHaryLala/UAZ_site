// Découpe une capture pleine page en tranches (pour l'inspecter visuellement).
// Usage : node tests/e2e/slice.mjs <image.png> [hauteur=1000]
import sharp from 'sharp';

const [file, height = '1000'] = process.argv.slice(2);
const { width, height: total } = await sharp(file).metadata();
const step = Number(height);
let index = 0;

for (let top = 0; top < total; top += step) {
    await sharp(file)
        .extract({ left: 0, top, width, height: Math.min(step, total - top) })
        .toFile(file.replace('.png', `-s${index++}.png`));
}

console.log(`${width}x${total} → ${index} tranches`);
