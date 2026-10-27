// Serveur local « proche production » pour mesurer les performances (Lighthouse) : sert public/ en statique avec
// compression Brotli/gzip et cache d'un an sur /build (comme Nginx, voir docs/DEPLOIEMENT.md) et relaie le reste vers
// `php artisan serve` (par défaut http://127.0.0.1:8000). Le serveur PHP intégré ne compresse rien : sans ce relais,
// les mesures seraient trop pessimistes.
// Usage : node tests/e2e/prod-like-server.mjs [port=8081] [amont=http://127.0.0.1:8000]
import fs from 'node:fs';
import http from 'node:http';
import path from 'node:path';
import zlib from 'node:zlib';

const port = Number(process.argv[2] ?? 8081);
const upstream = process.argv[3] ?? 'http://127.0.0.1:8000';
const root = path.resolve('public');

const types = {
    '.css': 'text/css; charset=utf-8', '.js': 'text/javascript; charset=utf-8', '.json': 'application/json', '.svg': 'image/svg+xml',
    '.woff2': 'font/woff2', '.webp': 'image/webp', '.avif': 'image/avif', '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg',
    '.ico': 'image/x-icon', '.pdf': 'application/pdf', '.txt': 'text/plain; charset=utf-8', '.xml': 'application/xml',
};
const compressible = /^(text\/|application\/(json|xml|javascript)|image\/svg)/;
const cache = new Map();

function encode(body, accept) {
    if (/\bbr\b/.test(accept)) return ['br', zlib.brotliCompressSync(body, { params: { [zlib.constants.BROTLI_PARAM_QUALITY]: 5 } })];
    if (/\bgzip\b/.test(accept)) return ['gzip', zlib.gzipSync(body, { level: 6 })];

    return [null, body];
}

http.createServer(async (req, res) => {
    const url = new URL(req.url, 'http://x');
    const file = path.join(root, decodeURIComponent(url.pathname));
    const accept = req.headers['accept-encoding'] ?? '';

    // Statique : /build, /storage, /images, favicons… (jamais index.php ni les dossiers)
    if ((req.method === 'GET' || req.method === 'HEAD') && file.startsWith(root) && fs.existsSync(file) && fs.statSync(file).isFile() && !file.endsWith('.php')) {
        const ext = path.extname(file).toLowerCase();
        const type = types[ext] ?? 'application/octet-stream';
        const headers = { 'Content-Type': type, Vary: 'Accept-Encoding' };
        headers['Cache-Control'] = url.pathname.startsWith('/build/') ? 'public, max-age=31536000, immutable' : 'public, max-age=2592000';
        let body = fs.readFileSync(file);

        if (compressible.test(type)) {
            const key = `${file}|${accept.includes('br') ? 'br' : 'gz'}|${fs.statSync(file).mtimeMs}`;
            if (!cache.has(key)) cache.set(key, encode(body, accept));
            const [encoding, packed] = cache.get(key);
            if (encoding) headers['Content-Encoding'] = encoding;
            body = packed;
        }
        headers['Content-Length'] = body.length;
        res.writeHead(200, headers).end(req.method === 'HEAD' ? undefined : body);

        return;
    }

    // Dynamique : relais vers PHP, avec le Host d'origine pour que les URLs générées restent sur ce serveur.
    try {
        const chunks = [];
        for await (const chunk of req) chunks.push(chunk);
        const response = await fetch(upstream + req.url, {
            method: req.method,
            headers: { ...req.headers, host: req.headers.host, 'accept-encoding': 'identity' },
            body: ['GET', 'HEAD'].includes(req.method) ? undefined : Buffer.concat(chunks),
            redirect: 'manual',
        });
        const headers = Object.fromEntries([...response.headers].filter(([k]) => !['content-encoding', 'content-length', 'transfer-encoding', 'connection', 'set-cookie'].includes(k)));
        let body = Buffer.from(await response.arrayBuffer());
        // Node (undici) ignore l'en-tête Host imposé : PHP génère donc des URLs vers l'amont. On les réécrit vers ce serveur
        // pour que la page, ses assets et sa CSP restent sur la même origine (comme derrière un vrai Nginx).
        if (/^text\/html|json|xml/.test(headers['content-type'] ?? '')) {
            body = Buffer.from(body.toString('utf8').replaceAll(upstream, `http://${req.headers.host}`).replaceAll(upstream.replaceAll('/', '\\/'), `http:\\/\\/${req.headers.host}`));
        }
        if (headers.location) headers.location = headers.location.replace(upstream, `http://${req.headers.host}`);
        if (compressible.test(headers['content-type'] ?? '')) {
            const [encoding, packed] = encode(body, accept);
            if (encoding) headers['content-encoding'] = encoding;
            body = packed;
        }
        headers.vary = 'Accept-Encoding';
        const cookies = response.headers.getSetCookie();
        res.writeHead(response.status, cookies.length ? { ...headers, 'set-cookie': cookies, 'content-length': body.length } : { ...headers, 'content-length': body.length });
        res.end(req.method === 'HEAD' ? undefined : body);
    } catch (error) {
        res.writeHead(502, { 'Content-Type': 'text/plain' }).end(`Amont injoignable : ${error.message}`);
    }
}).listen(port, '127.0.0.1', () => console.log(`Serveur proche production : http://127.0.0.1:${port}  →  ${upstream}`));
