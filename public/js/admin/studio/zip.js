/* Studio UAZ — un fichier ZIP « stocké » (sans compression : les PNG et JPEG sont déjà compressés), construit dans le navigateur. */
(() => {
    'use strict';

    const TABLE = (() => {
        const t = new Uint32Array(256);
        for (let n = 0; n < 256; n++) {
            let c = n;
            for (let k = 0; k < 8; k++) { c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1; }
            t[n] = c >>> 0;
        }

        return t;
    })();

    function crc32(octets) {
        let c = 0xffffffff;
        for (let i = 0; i < octets.length; i++) { c = TABLE[(c ^ octets[i]) & 0xff] ^ (c >>> 8); }

        return (c ^ 0xffffffff) >>> 0;
    }

    /** fichiers = [{ nom, blob }] ; renvoie un Blob « application/zip ». */
    async function zip(fichiers) {
        const encodeur = new TextEncoder();
        const morceaux = [];
        const centrale = [];
        let decalage = 0;
        const maintenant = new Date();
        const heure = (maintenant.getHours() << 11) | (maintenant.getMinutes() << 5) | (maintenant.getSeconds() >> 1);
        const date = ((maintenant.getFullYear() - 1980) << 9) | ((maintenant.getMonth() + 1) << 5) | maintenant.getDate();

        for (const f of fichiers) {
            const donnees = new Uint8Array(await f.blob.arrayBuffer());
            const nom = encodeur.encode(f.nom);
            const crc = crc32(donnees);
            const entete = new DataView(new ArrayBuffer(30));
            entete.setUint32(0, 0x04034b50, true); entete.setUint16(4, 20, true); entete.setUint16(6, 0x0800, true); entete.setUint16(8, 0, true);
            entete.setUint16(10, heure, true); entete.setUint16(12, date, true); entete.setUint32(14, crc, true);
            entete.setUint32(18, donnees.length, true); entete.setUint32(22, donnees.length, true); entete.setUint16(26, nom.length, true); entete.setUint16(28, 0, true);
            morceaux.push(entete.buffer, nom, donnees);

            const entree = new DataView(new ArrayBuffer(46));
            entree.setUint32(0, 0x02014b50, true); entree.setUint16(4, 20, true); entree.setUint16(6, 20, true); entree.setUint16(8, 0x0800, true); entree.setUint16(10, 0, true);
            entree.setUint16(12, heure, true); entree.setUint16(14, date, true); entree.setUint32(16, crc, true);
            entree.setUint32(20, donnees.length, true); entree.setUint32(24, donnees.length, true); entree.setUint16(28, nom.length, true);
            entree.setUint32(42, decalage, true);
            centrale.push(entree.buffer, nom);
            decalage += 30 + nom.length + donnees.length;
        }

        let tailleCentrale = 0;
        centrale.forEach((m) => { tailleCentrale += m.byteLength; });
        const fin = new DataView(new ArrayBuffer(22));
        fin.setUint32(0, 0x06054b50, true); fin.setUint16(8, fichiers.length, true); fin.setUint16(10, fichiers.length, true);
        fin.setUint32(12, tailleCentrale, true); fin.setUint32(16, decalage, true);

        return new Blob([...morceaux, ...centrale, fin.buffer], { type: 'application/zip' });
    }

    window.StudioZip = { zip };
})();
