<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * Le comptage des visites (migration 2026_11_19_120000) note aussi, pour chaque page vue, une localisation approximative (pays, région, ville) déduite de
 * l'adresse IP avec une base hébergée sur le serveur, ainsi que l'appareil et le navigateur, et regroupe les pages d'un visiteur dans une même journée.
 * La page « Confidentialité et cookies » doit le dire. Seules les phrases posées par la migration précédente sont remplacées : un texte réécrit
 * dans le back-office n'est jamais modifié.
 */
return new class extends Migration
{
    private const OLD_FR_SHORT = 'Il compte seulement, de façon anonyme, le nombre de visiteurs (voir « Statistiques de fréquentation » plus bas). ';

    private const NEW_FR_SHORT = 'Il compte, de façon anonyme, les visiteurs et les pages qu\'ils consultent, avec leur localisation approximative (voir « Statistiques de fréquentation » plus bas). ';

    private const OLD_EN_SHORT = 'It only counts visitors anonymously (see “Visit statistics” below). ';

    private const NEW_EN_SHORT = 'It counts visitors and the pages they view anonymously, with their approximate location (see “Visit statistics” below). ';

    private const OLD_FR = "<p>Pour savoir combien de personnes consultent le site, le serveur compte chaque page vue. <strong>Aucun cookie n'est déposé</strong> et <strong>votre adresse IP n'est pas conservée</strong> : elle sert seulement à calculer, avec le nom de votre navigateur et une clé qui change chaque jour, une empreinte chiffrée et non réversible, qui ne permet ni de vous retrouver ni de vous suivre d'un jour à l'autre.</p>"
        ."<p>Nous enregistrons aussi l'adresse de la page vue (sans ce qui suit le « ? »), la langue et, si vous venez d'un autre site, le nom de ce site. Les visites des robots et de l'espace d'administration ne sont pas comptées. Ces données ne servent qu'aux chiffres du tableau de bord de l'équipe du site et sont supprimées au bout de 400 jours.</p>";

    private const NEW_FR = "<p>Pour savoir combien de personnes consultent le site et ce qui les intéresse, le serveur note chaque page vue. <strong>Aucun cookie n'est déposé</strong> et <strong>votre adresse IP n'est pas conservée</strong> : elle sert seulement à calculer, avec le nom de votre navigateur et une clé qui change chaque jour, un numéro chiffré et non réversible. Ce numéro regroupe les pages que vous consultez pendant une journée ; il change le lendemain, et personne ne peut vous suivre d'un jour à l'autre.</p>"
        ."<p>Nous enregistrons aussi <strong>votre localisation approximative</strong> (pays, région et ville), déduite de votre adresse IP avec une base de données qui se trouve sur notre serveur : votre adresse n'est envoyée à aucun service extérieur. Ce n'est pas votre emplacement exact, mais en général celui de votre fournisseur d'accès Internet. Nous gardons aussi le type d'appareil (téléphone, tablette ou ordinateur), le nom de votre navigateur, l'adresse de chaque page vue (sans ce qui suit le « ? »), la langue et, si vous venez d'un autre site, le nom de ce site.</p>"
        .'<p>Les visites des robots et des personnes connectées à l\'administration ne sont pas comptées. Ces informations ne sont consultables que par les personnes autorisées de l\'équipe du site, sans nom, e-mail ni adresse IP, et sont supprimées au bout de 400 jours. '
        .'Base de localisation : <a href="https://db-ip.com" target="_blank" rel="noopener">IP Geolocation by DB-IP</a> (licence Creative Commons Attribution 4.0).</p>';

    private const OLD_EN = '<p>To know how many people visit the site, the server counts each page view. <strong>No cookie is set</strong> and <strong>your IP address is not kept</strong>: it is only used to compute, together with the name of your browser and a key that changes every day, an encrypted, non-reversible fingerprint that can neither identify you nor follow you from one day to the next.</p>'
        .'<p>We also record the address of the page viewed (without anything after the “?”), the language and, if you come from another site, the name of that site. Visits by robots and from the administration area are not counted. This data is only used for the figures on the site team’s dashboard and is deleted after 400 days.</p>';

    private const NEW_EN = '<p>To know how many people visit the site and what interests them, the server notes each page view. <strong>No cookie is set</strong> and <strong>your IP address is not kept</strong>: it is only used to compute, together with the name of your browser and a key that changes every day, an encrypted, non-reversible number. This number groups the pages you view during one day; it changes the next day, and nobody can follow you from one day to the next.</p>'
        .'<p>We also record <strong>your approximate location</strong> (country, region and city), deduced from your IP address with a database stored on our own server: your address is not sent to any outside service. It is not your exact position, but usually that of your Internet provider. We also keep the type of device (phone, tablet or computer), the name of your browser, the address of each page viewed (without anything after the “?”), the language and, if you come from another site, the name of that site.</p>'
        .'<p>Visits by robots and by people logged in to the administration are not counted. This information can only be seen by the authorised members of the site team, without name, email or IP address, and is deleted after 400 days. '
        .'Location database: <a href="https://db-ip.com" target="_blank" rel="noopener">IP Geolocation by DB-IP</a> (Creative Commons Attribution 4.0 licence).</p>';

    public function up(): void
    {
        $replacements = [
            self::OLD_FR_SHORT => self::NEW_FR_SHORT,
            self::OLD_EN_SHORT => self::NEW_EN_SHORT,
            self::OLD_FR => self::NEW_FR,
            self::OLD_EN => self::NEW_EN,
        ];

        $replace = function (mixed $value) use (&$replace, $replacements): mixed {
            if (is_array($value)) {
                return array_map($replace, $value);
            }

            return is_string($value) ? strtr($value, $replacements) : $value;
        };

        Page::query()->where('key', 'privacy')->get()->each(function (Page $page) use ($replace) {
            $changed = false;

            foreach (['blocks', 'draft', 'translations'] as $attribute) {
                $current = $page->getAttribute($attribute);

                if (! is_array($current)) {
                    continue;
                }

                $updated = $replace($current);

                if ($updated !== $current) {
                    $page->setAttribute($attribute, $updated);
                    $changed = true;
                }
            }

            if ($changed) {
                $page->save();
            }
        });
    }

    public function down(): void
    {
        // Le texte d'avant décrivait moins de choses que le site n'en enregistre : on ne le rétablit pas.
    }
};
